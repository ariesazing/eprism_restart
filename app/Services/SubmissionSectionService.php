<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\SubmissionTemplates\SubmissionTemplate;
use Illuminate\Support\Collection;

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
            'content_html' => $this->sanitizeRichText($value['html'] ?? null),
        ]);
    }

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

                return $definition->type === 'table'
                    ? $section->tableRows() === []
                    : trim((string) strip_tags((string) $section->content_html)) === '';
            })
            ->map(fn ($definition) => $definition->label)
            ->values();
    }

    private const ALLOWED_HTML = 'p[style],br,strong,b,em,i,u,s,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],'
        .'ul,ol,li,a[href],blockquote,hr,'
        .'table[style],colgroup,col[style],thead,tbody,tr,th[style],td[style|colspan|rowspan],sub,sup,'
        .'span[style],img[src|alt|width|height]';

    private const ALLOWED_CSS_PROPERTIES = [
        'font-weight', 'font-style', 'text-decoration', 'text-align', 'color', 'background-color',
        'font-family', 'font-size', 'line-height', 'vertical-align',
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
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'data' => true]);
        $config->set('Cache.DefinitionImpl', null);

        $clean = trim((new \HTMLPurifier($config))->purify($value));

        return $clean === '' ? null : $clean;
    }
}
