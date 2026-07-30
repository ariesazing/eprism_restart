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
     * @param  array<string, mixed>  $sectionInputs  section_key => string (rich_text) | array of rows (table)
     */
    public function save(ResearchSubmission $submission, SubmissionTemplate $template, array $sectionInputs): void
    {
        $sections = $this->ensureSections($submission, $template)->keyBy('section_key');

        foreach ($template->sections as $definition) {
            $section = $sections->get($definition->key);
            $value = $sectionInputs[$definition->key] ?? null;

            if ($definition->type === 'table') {
                $rows = collect((array) $value)
                    ->map(fn ($row) => collect($definition->columns)
                        ->mapWithKeys(fn ($column) => [$column['key'] => trim((string) ($row[$column['key']] ?? ''))])
                        ->all())
                    ->filter(fn ($row) => collect($row)->contains(fn ($cell) => $cell !== ''))
                    ->values();

                $section->update(['content' => $rows->isEmpty() ? null : $rows->toJson()]);

                continue;
            }

            $section->update(['content' => $this->sanitizeRichText($value)]);
        }
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
                    : trim((string) strip_tags((string) $section->content)) === '';
            })
            ->map(fn ($definition) => $definition->label)
            ->values();
    }

    private const ALLOWED_RICH_TEXT_TAGS = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><ul><ol><li><a><blockquote><table><thead><tbody><tr><th><td><sub><sup>';

    private function sanitizeRichText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags($value, self::ALLOWED_RICH_TEXT_TAGS);
        $value = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $value);
        $value = preg_replace('/(href|src)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')/i', '', $value);
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
