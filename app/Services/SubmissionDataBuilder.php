<?php

namespace App\Services;

use App\Models\ResearchProponent;
use App\Models\ResearchSubmission;
use App\SubmissionTemplates\SubmissionTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the ${key} scalar + each-block data DocxTemplateFiller needs to fill an admin-authored
 * front-matter .docx template — the docx counterpart to SubmissionHtmlTemplateRenderer's
 * buildScalars()/buildEachContexts() for the two HTML-template engines. Deliberately excludes a
 * rich_text chapter's own content entirely: DocxTemplateFiller's plain string substitution
 * can't insert a whole other docx's worth of paragraphs/images/styles into a `${key}` token
 * (see its own class doc) — a rich_text chapter's own placeholder is instead left untouched
 * here and filled afterward by ManuscriptProcessor's `assemble_chapters` operation (see
 * SubmissionDocxComposer::composeManuscript() and scripts/manuscript.py), which splices that
 * chapter's own .docx in at that exact paragraph via docxcompose. Only scalars, the proponents
 * each-block, and table-type chapters' each-blocks are ever needed here.
 */
class SubmissionDataBuilder
{
    private const RESEARCH_TYPE_LABELS = ['basic' => 'Basic Research', 'action' => 'Action Research'];

    private const CLASSIFICATION_LABELS = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];

    /**
     * @return array{scalars: array<string, string>, each: array<string, array{columns: array<string, string>, rows: array<int, array<string, string>>}>}
     */
    public function build(ResearchSubmission $submission): array
    {
        $submission->loadMissing('proponents');
        $template = $submission->template();
        $sections = $submission->sections()->orderBy('sort_order')->get()->keyBy('section_key');

        return [
            'scalars' => $this->buildScalars($submission, $template),
            'each' => $this->buildEach($submission, $template, $sections),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildScalars(ResearchSubmission $submission, SubmissionTemplate $template): array
    {
        return [
            'title' => $submission->title,
            'research_type_label' => self::RESEARCH_TYPE_LABELS[$submission->research_type] ?? $submission->research_type,
            'classification_label' => self::CLASSIFICATION_LABELS[$submission->classification] ?? $submission->classification,
            'organizational_unit' => $submission->organizational_unit ?? '',
            'school_id' => $submission->school_id ?? '',
            'template_label' => $template->label,
            'generated_at' => now()->format('F j, Y g:i A'),
        ];
    }

    /**
     * @return array<string, array{columns: array<string, string>, rows: array<int, array<string, string>>}>
     */
    private function buildEach(ResearchSubmission $submission, SubmissionTemplate $template, Collection $sections): array
    {
        $each = [
            'proponents' => [
                'columns' => array_fill_keys(array_keys((new ResearchProponent)->documentFields(1)), 'text') + ['proponent_photo' => 'image'],
                'rows' => $submission->proponents->values()->map(fn (ResearchProponent $proponent, int $index) => $proponent->documentFields($index + 1) + [
                    'proponent_photo' => $this->photoPath($proponent),
                ])->all(),
            ],
        ];

        foreach ($template->sections as $definition) {
            if ($definition->type === 'table') {
                $each[$definition->key] = [
                    'columns' => collect($definition->columns)->mapWithKeys(fn ($column) => [$column['key'] => 'text'])->all(),
                    'rows' => $sections->get($definition->key)?->tableRows() ?? [],
                ];
            }
        }

        return $each;
    }

    /**
     * PHPWord's setImageValue() needs a real file path, not a data URI (unlike the HTML
     * template engine's own photoDataUri(), which the browser can render inline).
     */
    private function photoPath(ResearchProponent $proponent): string
    {
        if (! $proponent->photo_path || ! Storage::disk('local')->exists($proponent->photo_path)) {
            return '';
        }

        return Storage::disk('local')->path($proponent->photo_path);
    }
}
