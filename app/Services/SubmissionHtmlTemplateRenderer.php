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
    private const RESEARCH_TYPE_LABELS = ['basic' => 'Basic Research', 'action' => 'Action Research'];

    private const CLASSIFICATION_LABELS = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];

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
        return preg_replace_callback(
            '/\{\{#each\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/each\}\}/s',
            function (array $match) use ($each) {
                [, $key, $fragment] = $match;
                $rows = $each[$key] ?? [];

                $rendered = '';
                foreach ($rows as $row) {
                    $rendered .= preg_replace_callback(
                        '/\$\{([a-zA-Z0-9_]+)\}/',
                        fn (array $inner) => array_key_exists($inner[1], $row) ? e($row[$inner[1]]) : $inner[0],
                        $fragment
                    );
                }

                return $rendered;
            },
            $html
        );
    }

    /**
     * canvas-editor's table element has no way to represent stray text sitting between
     * <tr> elements, so a {{#each key}}...{{/each}} wrapper placed around a table's
     * repeating row (as the seeded fixtures originally did) is silently dropped the
     * moment an admin opens and saves the template through the WYSIWYG editor — verified
     * by round-tripping a seeded template through it, which left a bare row of literal
     * ${col} placeholders with no {{#each}}/{{/each}} around it at all. renderEachBlocks()
     * already handles the pristine, still-wrapped case; this is the fallback for the
     * (now far more common) already-corrupted case: any <tr> whose cells reference every
     * column of a table section is treated as that section's repeating row template,
     * independent of whether {{#each}} markup survived around it.
     *
     * @param  array<string, array<int, array<string, string>>>  $each
     */
    private function renderBareTableRows(string $html, SubmissionTemplate $template, array $each): string
    {
        foreach ($template->sections as $definition) {
            if ($definition->type !== 'table') {
                continue;
            }

            $columnKeys = array_column($definition->columns, 'key');
            $rows = $each[$definition->key] ?? [];
            $replaced = false;

            $html = preg_replace_callback(
                '/<tr\b[^>]*>.*?<\/tr>/is',
                function (array $match) use ($columnKeys, $rows, &$replaced) {
                    if ($replaced || ! collect($columnKeys)->every(fn ($key) => str_contains($match[0], '${'.$key.'}'))) {
                        return $match[0];
                    }

                    $replaced = true;

                    $rendered = '';
                    foreach ($rows as $row) {
                        $rendered .= preg_replace_callback(
                            '/\$\{([a-zA-Z0-9_]+)\}/',
                            fn (array $inner) => array_key_exists($inner[1], $row) ? e($row[$inner[1]]) : $inner[0],
                            $match[0]
                        );
                    }

                    return $rendered;
                },
                $html
            );
        }

        return $html;
    }

    /**
     * @param  array<string, array{value: string, raw: bool}>  $scalars
     */
    private function renderScalars(string $html, array $scalars): string
    {
        return preg_replace_callback(
            '/\$\{([a-zA-Z0-9_]+)\}/',
            function (array $match) use ($scalars) {
                if (! array_key_exists($match[1], $scalars)) {
                    return $match[0];
                }

                $entry = $scalars[$match[1]];

                return $entry['raw'] ? $entry['value'] : e($entry['value']);
            },
            $html
        );
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
