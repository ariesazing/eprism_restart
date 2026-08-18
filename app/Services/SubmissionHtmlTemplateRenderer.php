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

        $html = $this->renderEachBlocks($templateHtml, $this->buildEachContexts($submission, $template, $sections));

        return $this->renderScalars($html, $this->buildScalars($submission, $template, $sections));
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
                $scalars[$definition->key] = ['value' => $sections->get($definition->key)?->content_html ?? '', 'raw' => true];
            }
        }

        return $scalars;
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
}
