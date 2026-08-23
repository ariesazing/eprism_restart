<?php

namespace App\Services;

use App\Models\ResearchProponent;
use App\Models\ResearchSubmission;
use App\SubmissionTemplates\SubmissionTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Fills an admin-authored HTML template (SubmissionDocumentTemplate::content) with a
 * submission's data. Deliberately simple string substitution, not a full templating
 * language — admin-authored content is never evaluated as code, only searched/replaced,
 * so an edited template can't become a code-injection surface.
 *
 * Two placeholder forms:
 *   - ${key}                          scalar substitution (HTML-escaped, unless it's a
 *                                      rich_text section's HTML mirror, which is already HTML)
 *   - {{#each key}}...${col}...{{/each}}  clones the fragment once per row in `key`
 *                                      (proponents, or a table-type section's rows),
 *                                      with ${col} scoped to the current row/proponent
 *
 * Placeholders with no matching data are left as literal text rather than blanked, so an
 * admin editing a template can see their own mistakes instead of silently losing content.
 */
class SubmissionHtmlTemplateRenderer
{
    /**
     * The exact base64 payload of PROPONENT_PHOTO_PLACEHOLDER_SRC in
     * resources/js/document-editor/toolbar.js ("Insert > Proponent photo placeholder") —
     * keep both copies byte-identical. substitutePhotoPlaceholder() looks for an <img> whose
     * `src` contains this to find the placeholder inside each proponent's rendered row and
     * swap in their real photo. canvas-editor is WYSIWYG only, so a typed `${proponent_photo}`
     * token can never become a real <img> — it can only ever render as its own value (the
     * photo's raw base64 data URI, as visible text), which is why this exists as an actual
     * insertable image instead. PNG, not SVG: the sanitizer's HTMLPurifier config strips
     * `data:image/svg+xml` URIs outright (SVG can carry a <script>, so its data-URI allowlist
     * only trusts raster formats) — verified by round-tripping an SVG version through
     * SubmissionSectionService::sanitizeRichText(), which silently deleted the whole <img>.
     */
    private const PROPONENT_PHOTO_MARKER = 'iVBORw0KGgoAAAANSUhEUgAAAHgAAACWCAYAAAAVKkwgAAAACXBIWXMAAA7EAAAOxAGVKw4bAAADZUlEQVR4nO3dQW4TQRCF4Q7iUFyALYdlywVyjqxYcgNYtXBGtmfGU9311+v3r5Fc875YCUg4bx+///xtTrYv2Qe4sRlYPAOLZ2DxDCyegcUzsHgGFs/A4hlYPAOLZ2DxDCyegcUzsHgGFs/A4hlYPAOLZ2DxDCyegcUzsHgGFs/A4hlYPAOL9zX7gNH9/PW++2d+fP824ZKc3tT+89kR0L2UwGWAI2C3KUCXBx4Bu60ydFngGbDbKkKX/Ck6Azfzda9UDjh75OzXP1spYMq4lDuOVAaYNirtnkeVAKaOSb3rNjwwfUT6fWhg+ng98p1YYPJo96LeiwV2MSGBqe+GvYh3I4FdXDhg4rvgTLT7ccAuNhQw7av/1UjPgQJ28RlYPAOLhwEmfd+KiPI8GGA3JgOLZ2DxDCyegcUzsHgGFs/A4mGAK/63kGdRngcD7MZkYPEMLB4KmPJ962qk50ABu/hwwKSv/lei3Y8DdrEhgWnvgqMR70YCu7iwwMR3w7Oo92KBW+OOto18Jxq4NfZ4rfHvwwO3xh2RetdtJYBb441Ju+dRZYBb44xKueNIpYBbyx83+/XPVg64tbyRq+G2VvjDSHv+tNnnlQfu+fOi7ycD3PMnvn9ODnibf2eDOPDqlfwp2h3PwOIZWDwDi2dg8QwsnoHFM7B4BhbPwOIZWDwDi7ccMOUzJGe1FHDHXQl5GeAt6irISwA/wlwBWR54D1EdWRr4KJ4ysjTwmVSRZYFVwc4mCfwqruIXhRzwVSQ1ZCngKBwlZBngaBQVZAngURgKyOWBRyNURy4NPGv8yshlgWePXhW5JHDW2BWRywFnj5z9+mcrB0yoEnIp4ErDUioDTMOl3fOoEsDUMal33YYHpo9Ivw8NTB+vR74TC0we7V7Ue5HA1LH2It6NAyaOdCba/Shg2jivRnoODDBplIgoz4MApowRHeG5EMDKZSOnA2cPoF4q8Cq4mc+ZBrwKbi/reVOAV8PtZTz3dOBVcXuzn38q8Oq4vZk7TAM27udm7TEF2Lj3m7HLcGDjPm/0PkOBjXuskTsNAzbuuUbtlf5Ple5/I5CHAPvdyykc2LjXit4vFNi4MUXuGAZs3Nii9gwBNu6YIna9DGzcsV3d1799VDz/PVg8A4tnYPEMLJ6BxTOweP8AIoRRk26vajEAAAAASUVORK5CYII=';

    private const RESEARCH_TYPE_LABELS = ['basic' => 'Basic Research', 'action' => 'Action Research'];

    private const CLASSIFICATION_LABELS = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];

    public function __construct(
        private readonly PlaceholderEngine $engine,
    ) {}

    /**
     * Renders one zone (body, header, or footer) of a submission's document template.
     * All three zones share the same placeholder vocabulary — header/footer aren't a
     * separate, more-limited template anymore, they're part of the same document.
     */
    public function render(string $templateHtml, ResearchSubmission $submission): string
    {
        $submission->loadMissing('proponents');
        $template = $submission->template();
        $sections = $submission->sections()->orderBy('sort_order')->get()->keyBy('section_key');
        $each = $this->buildEachContexts($submission, $template, $sections);

        $html = $this->renderEachBlocks($templateHtml, $each);
        $html = $this->renderBareTableRows($html, $template, $each);
        $html = $this->renderScalars($html, $this->buildScalars($submission, $template, $sections));

        return $this->inlineTemplateImages($html);
    }

    private function buildEachContexts(ResearchSubmission $submission, SubmissionTemplate $template, Collection $sections): array
    {
        $each = [
            'proponents' => $submission->proponents->map(fn (ResearchProponent $proponent) => [
                'proponent_name' => trim("{$proponent->last_name}, {$proponent->first_name} ".($proponent->middle_initial ? "{$proponent->middle_initial}." : '')),
                'proponent_position' => $proponent->position ?? '',
                'proponent_photo' => $this->photoDataUri($proponent) ?? '',
            ])->all(),
        ];

        foreach ($template->sections as $definition) {
            if ($definition->type === 'table') {
                $each[$definition->key] = $sections->get($definition->key)?->tableRows() ?? [];
            }
        }

        return $each;
    }

    private function buildScalars(ResearchSubmission $submission, SubmissionTemplate $template, Collection $sections): array
    {
        $scalars = [
            'title' => ['value' => $submission->title, 'raw' => false],
            'research_type_label' => ['value' => self::RESEARCH_TYPE_LABELS[$submission->research_type] ?? $submission->research_type, 'raw' => false],
            'classification_label' => ['value' => self::CLASSIFICATION_LABELS[$submission->classification] ?? $submission->classification, 'raw' => false],
            'organizational_unit' => ['value' => $submission->organizational_unit ?? '', 'raw' => false],
            'school_id' => ['value' => $submission->school_id ?? '', 'raw' => false],
            'template_label' => ['value' => $template->label, 'raw' => false],
            'generated_at' => ['value' => now()->format('F j, Y g:i A'), 'raw' => false],
        ];

        foreach ($template->sections as $definition) {
            if ($definition->type !== 'table') {
                $content = $sections->get($definition->key)?->content_html ?? '';
                $scalars[$definition->key] = ['value' => $this->paragraphize($content), 'raw' => true];
            }
        }

        return $scalars;
    }

    /**
     * canvas-editor's plain-paragraph flow has no block-level wrapper at all — a chapter's
     * text comes out of getHTML() as one flat run of inline <span> elements broken only by
     * manual <br/> tags, never real <p> boundaries. Left that way, dompdf treats it as one
     * giant inline block and its page-break logic doesn't reliably respect a custom @page
     * margin-bottom for it — verified by reproducing in isolation: the same text split into
     * separate <p> blocks paginates correctly against an identical margin, but as one long
     * span/br chain it overflows into (and sometimes past) the reserved footer area. Splitting
     * on <br> and re-wrapping each run in its own <p> gives dompdf real block-level break
     * points, without changing what's actually displayed.
     *
     * Segments that are already a real block element (a table, list, or heading — all of
     * which canvas-editor CAN emit for richer content) are left alone rather than wrapped,
     * since nesting a block inside a <p> is invalid and would just get silently unwound by
     * the HTML parser anyway.
     */
    private function paragraphize(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $segments = preg_split('/(?:<span[^>]*>\s*)?<br\s*\/?>(?:\s*<\/span>)?/i', $html) ?: [$html];

        return collect($segments)
            ->map(fn (string $segment) => trim($segment))
            ->filter(fn (string $segment) => $segment !== '')
            ->map(fn (string $segment) => preg_match('/^<(table|ul|ol|h[1-6]|p)\b/i', $segment) === 1
                ? $segment
                : "<p>{$segment}</p>")
            ->implode('');
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $each
     */
    private function renderEachBlocks(string $html, array $each): string
    {
        return $this->engine->substituteEachBlocks($html, $each, function (string $key, string $rowHtml, array $row) {
            return $key === 'proponents'
                ? $this->substitutePhotoPlaceholder($rowHtml, $row['proponent_photo'] ?? '')
                : $rowHtml;
        });
    }

    /**
     * Finds the "Insert > Proponent photo placeholder" image (identified by
     * PROPONENT_PHOTO_MARKER in its own `src`, not its position) within one already-
     * substituted proponent row and swaps in that proponent's real photo, preserving
     * whatever width/height/position the admin gave the placeholder in the editor. A
     * proponent with no uploaded photo keeps the placeholder graphic itself — a visibly
     * intentional "no photo" state rather than a broken image.
     */
    private function substitutePhotoPlaceholder(string $rowHtml, string $photoDataUri): string
    {
        if ($photoDataUri === '') {
            return $rowHtml;
        }

        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            fn (array $match) => str_contains($match[0], self::PROPONENT_PHOTO_MARKER)
                ? preg_replace('/\ssrc="[^"]*"/i', ' src="'.$photoDataUri.'"', $match[0], 1)
                : $match[0],
            $rowHtml
        );
    }

    /**
     * Fallback for when canvas-editor has dropped the {{#each}}/{{/each}} wrapper around a
     * table section's repeating row — see PlaceholderEngine::substituteBareTableRows() for
     * why this is needed at all.
     *
     * @param  array<string, array<int, array<string, string>>>  $each
     */
    private function renderBareTableRows(string $html, SubmissionTemplate $template, array $each): string
    {
        $columnKeysByKey = [];

        foreach ($template->sections as $definition) {
            if ($definition->type === 'table') {
                $columnKeysByKey[$definition->key] = array_column($definition->columns, 'key');
            }
        }

        return $this->engine->substituteBareTableRows($html, $each, $columnKeysByKey);
    }

    /**
     * @param  array<string, array{value: string, raw: bool}>  $scalars
     */
    private function renderScalars(string $html, array $scalars): string
    {
        return $this->engine->substituteScalars($html, $scalars);
    }

    private function photoDataUri(ResearchProponent $proponent): ?string
    {
        if (! $proponent->photo_path || ! Storage::disk('local')->exists($proponent->photo_path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($proponent->photo_path);
        $mime = Storage::disk('local')->mimeType($proponent->photo_path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * Images inserted via the template editor's toolbar are stored as real files and
     * referenced by URL (see DocumentTemplateController::uploadImage) — small enough to
     * save without tripping database packet-size limits. dompdf still needs the actual
     * bytes to render them into a PDF, so this inlines them as base64 here, at render
     * time only — never persisted back to the template's stored HTML.
     */
    private function inlineTemplateImages(string $html): string
    {
        return preg_replace_callback(
            '/<img\s([^>]*\s)?src="[^"]*\/document-templates\/images\/([a-zA-Z0-9._-]+)"([^>]*)>/i',
            function (array $match) {
                $path = 'template-images/'.$match[2];

                if (! Storage::disk('local')->exists($path)) {
                    return $match[0];
                }

                $contents = Storage::disk('local')->get($path);
                $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
                $dataUri = 'data:'.$mime.';base64,'.base64_encode($contents);

                return '<img '.($match[1] ?? '').'src="'.$dataUri.'"'.$match[3].'>';
            },
            $html
        );
    }
}
