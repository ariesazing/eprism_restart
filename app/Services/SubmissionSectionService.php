<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\SubmissionSection as SubmissionSectionModel;
use App\SubmissionTemplates\SubmissionTemplate;
use App\Support\Html\WebpAllowedDataUriScheme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionSectionService
{
    /**
     * Make sure a submission has one section row per template section, in template order.
     */
    public function ensureSections(ResearchSubmission $submission, SubmissionTemplate $template): Collection
    {
        $existing = $submission->sections()->get()->keyBy('section_key');

        foreach ($template->sections as $index => $definition) {
            if ($existing->has($definition->key)) {
                continue;
            }

            $existing->put($definition->key, $submission->sections()->create([
                'section_key' => $definition->key,
                'label' => $definition->label,
                'type' => $definition->type,
                'content' => null,
                'sort_order' => $index,
            ]));
        }

        return $template->sections !== []
            ? collect($template->sections)->map(fn ($definition) => $existing->get($definition->key))
            : collect();
    }

    /**
     * Persist submitted section content against the submission's template.
     *
     * @param  array<string, mixed>  $sectionInputs  section_key => array of rows (table) |
     *                                               array{content?: string, html?: string} (rich_text —
     *                                               canvas-editor's JSON + its HTML mirror)
     */
    public function save(ResearchSubmission $submission, SubmissionTemplate $template, array $sectionInputs): void
    {
        $sections = $this->ensureSections($submission, $template)->keyBy('section_key');

        foreach ($template->sections as $definition) {
            $this->saveSection($sections->get($definition->key), $definition, $sectionInputs[$definition->key] ?? null);
        }
    }

    /**
     * Persist a single section's content without touching any of the submission's other
     * sections — the autosave path's counterpart to save(): save() expects every template
     * section's current value in one call (a section left out gets nulled), which is exactly
     * right for a full form submit but wrong for a background autosave tick that only ever
     * has one freshly-edited chapter's content on hand.
     */
    public function saveOne(ResearchSubmission $submission, SubmissionTemplate $template, string $sectionKey, mixed $value): void
    {
        $definition = collect($template->sections)->firstWhere('key', $sectionKey);

        abort_unless($definition !== null, 404);

        $section = $this->ensureSections($submission, $template)->keyBy('section_key')->get($sectionKey);

        $this->saveSection($section, $definition, $value);
    }

    private function saveSection($section, $definition, mixed $value): void
    {
        if ($definition->type === 'table') {
            $rows = collect((array) $value)
                ->map(fn ($row) => collect($definition->columns)
                    ->mapWithKeys(fn ($column) => [$column['key'] => trim((string) ($row[$column['key']] ?? ''))])
                    ->all())
                ->filter(fn ($row) => collect($row)->contains(fn ($cell) => $cell !== ''))
                ->values();

            $section->update(['content' => $rows->isEmpty() ? null : $rows->toJson()]);

            return;
        }

        $section->update([
            'content' => $value['content'] ?? null,
            'content_html' => $this->withHeadroomForLargeEmbeddedImages(fn () => $this->sanitizeRichText($value['html'] ?? null)),
        ]);
    }

    /**
     * HTMLPurifier, like dompdf (see SubmissionPdfComposer::withHeadroomForLargeEmbeddedImages(),
     * the precedent this mirrors), decodes every embedded base64 <img> in the HTML it's given
     * into memory in full while sanitizing — a chapter with a few pasted images can exceed an
     * ordinary request's memory_limit, which is an uncatchable fatal error, not something a
     * try/catch around saveSection() could turn into a clean response. Scoped to just this
     * call (not the whole autosave request) since it runs on every ~2s autosave tick, far more
     * often than SubmissionPdfComposer's once-per-submit call — a smaller ceiling than that
     * 1024M is enough for one chapter's images rather than a whole composed manuscript.
     */
    private function withHeadroomForLargeEmbeddedImages(callable $callback): mixed
    {
        $previous = ini_get('memory_limit');

        ini_set('memory_limit', '512M');

        try {
            return $callback();
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    /**
     * Seeds a rich_text section's ONLYOFFICE-edited .docx the first time it's opened —
     * mirrors how a canvas-editor chapter simply starts out with `content: null` until the
     * researcher types something, except ONLYOFFICE needs an actual file to open rather than
     * an empty value. A no-op once onlyoffice_path is already set (every save after the
     * first rewrites the same path in place, see OnlyOfficeDocumentController::callback()).
     *
     * Deliberately blank, with no chapter title seeded into it: the admin's own front-matter
     * template owns chapter titles/order/placement entirely (its own literal heading text,
     * authored right alongside that chapter's `${<key>}` placeholder paragraph — see
     * DocumentTemplateController's placeholder sidebar and scripts/manuscript.py's
     * assemble_chapters()) — a researcher's own chapter document is purely their own body
     * content, spliced in at that placeholder, and must never duplicate a title the template
     * already provides.
     */
    public function ensureOnlyOfficeDocument(SubmissionSectionModel $section): void
    {
        if ($section->onlyoffice_path !== null) {
            return;
        }

        $path = "onlyoffice-documents/{$section->research_submission_id}/{$section->section_key}.docx";

        Storage::disk('local')->put($path, file_get_contents(resource_path('onlyoffice/blank-chapter.docx')));

        $section->update([
            'onlyoffice_path' => $path,
            'onlyoffice_key' => (string) Str::uuid(),
        ]);
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    public function missingRequiredSections(ResearchSubmission $submission, SubmissionTemplate $template): Collection
    {
        $sections = $submission->sections()->get()->keyBy('section_key');

        return collect($template->sections)
            ->filter(fn ($definition) => $definition->required)
            ->filter(function ($definition) use ($sections) {
                $section = $sections->get($definition->key);

                if (! $section) {
                    return true;
                }

                if ($definition->type === 'table') {
                    return $section->tableRows() === [];
                }

                return $this->sectionHasNoContent($section);
            })
            ->map(fn ($definition) => ['key' => $definition->key, 'label' => $definition->label])
            ->values();
    }

    /**
     * content_html is only ever a best-effort *mirror* of an ONLYOFFICE chapter's real content
     * — refreshed by a secondary docx-to-HTML conversion round-trip through Document Server
     * every time the chapter is saved (see OnlyOfficeDocumentController::refreshContentHtml()),
     * which silently leaves the previous (possibly still-blank) mirror in place if that
     * round-trip fails for any reason (a transient Document Server hiccup, a slow/unreachable
     * network — confirmed happening on this machine via ActivityLog's onlyoffice.conversion_failed
     * entries) rather than ever propagating the failure. That made readiness checks report a
     * chapter as empty indefinitely even after the researcher had genuinely typed real content
     * and it was safely saved to the chapter's own .docx — the docx save itself doesn't depend
     * on that secondary conversion at all. So for an ONLYOFFICE chapter, "has content" is
     * answered straight from its own saved .docx (a local file read, no Document Server round
     * trip, nothing to silently go stale) instead of the content_html mirror; a canvas_editor
     * chapter (no onlyoffice_path) is unaffected and keeps checking content_html exactly as
     * before.
     */
    private function sectionHasNoContent(SubmissionSectionModel $section): bool
    {
        return $section->onlyoffice_path !== null
            ? ! $this->onlyofficeDocxHasVisibleText($section->onlyoffice_path)
            : trim((string) strip_tags((string) $section->content_html)) === '';
    }

    /**
     * Every visible character a researcher typed lives inside a <w:t> text run in the docx's
     * own word/document.xml — reading that directly is the docx equivalent of
     * strip_tags($content_html) === '' for a canvas-editor chapter, without needing Document
     * Server reachable at all.
     */
    private function onlyofficeDocxHasVisibleText(string $storagePath): bool
    {
        if (! Storage::disk('local')->exists($storagePath)) {
            return false;
        }

        $zip = new \ZipArchive;

        if ($zip->open(Storage::disk('local')->path($storagePath)) !== true) {
            return false;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return false;
        }

        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);

        return trim(implode('', $matches[1] ?? [])) !== '';
    }

    // div[style] is load-bearing, not decorative: canvas-editor's getHTML() carries a
    // row's non-left text-align *only* on that row's wrapping <div> (the per-run <span>
    // also sets text-align, but that's inert on an inline element in any CSS renderer) —
    // without it here, HTMLPurifier unwraps/strips the div and centered/right/justified
    // paragraphs silently revert to left-aligned once sanitized (see
    // SubmissionHtmlTemplateRenderer::paragraphize(), which also needs to treat a
    // surviving div as block-level).
    private const ALLOWED_HTML = 'p[style],div[style],br,strong,b,em,i,u,s,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],'
        .'ul,ol,li,a[href],blockquote,hr,'
        .'table[style],colgroup,col[style],thead,tbody,tr,th[style],td[style|colspan|rowspan],sub,sup,'
        .'span[style],img[src|alt|width|height]';

    // Note: canvas-editor also sets text-align-last:justify (so a justified paragraph's
    // last line stretches too), but HTMLPurifier has no built-in CSS definition for that
    // property at all — unlike text-align, listing it here wouldn't be enough to keep it
    // (HTMLPurifier drops any property it has no AttrDef for regardless of the allow-list).
    // Skipped as a minor, cosmetic gap (the paragraph still justifies correctly; only its
    // last line falls back to normal left-alignment) rather than reaching into
    // HTMLPurifier's internals to register a custom property definition.
    private const ALLOWED_CSS_PROPERTIES = [
        'font-weight', 'font-style', 'text-decoration', 'text-align', 'color', 'background-color',
        'font-family', 'font-size', 'line-height', 'vertical-align', 'display',
        'border', 'border-color', 'border-style', 'border-width', 'width', 'height',
    ];

    /**
     * Sanitizes rich-text HTML (canvas-editor's `getHTML()` mirror) before it's
     * persisted. canvas-editor leans on inline `style` and `<span>` for
     * formatting (bold/italic/color/alignment), so this allow-list is broader
     * than a simple tag whitelist — HTMLPurifier is used instead of
     * hand-rolled strip_tags so that broader allow-list doesn't become an XSS
     * surface (no <script>, no event handlers, no javascript: URLs).
     */
    public function sanitizeRichText(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::ALLOWED_HTML);
        $config->set('CSS.AllowedProperties', self::ALLOWED_CSS_PROPERTIES);
        // display is one of the CSS properties HTMLPurifier only recognizes when this is
        // on (see HTMLPurifier_CSSDefinition::doSetupTricky) — needed for the
        // display:inline-block tab-width span below to survive sanitization at all.
        $config->set('CSS.AllowTricky', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'data' => true]);
        $config->set('Cache.DefinitionImpl', null);

        // HTMLPurifier's stock data: URI validator only allows image/jpeg, /gif, and /png —
        // a pasted chapter image is WebP (see resources/js/document-editor/index.js's
        // capPastedImageSize()), which would otherwise be silently stripped out here.
        \HTMLPurifier_URISchemeRegistry::instance()->register('data', new WebpAllowedDataUriScheme);

        $clean = trim((new \HTMLPurifier($config))->purify($value));

        return $clean === '' ? null : $clean;
    }
}
