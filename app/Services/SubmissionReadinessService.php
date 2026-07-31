<?php

namespace App\Services;

use App\Models\ResearchSubmission;

class SubmissionReadinessService
{
    public function __construct(
        private readonly SubmissionSectionService $sections,
    ) {}

    /**
     * Assess how complete a submission is against its template's required
     * sections and attachments, for the submit-blocking check and the
     * researcher dashboard's readiness display.
     *
     * @return array{sections: array{total: int, done: int, missing: array<int, string>}, attachments: array{total: int, done: int, missing: array<int, string>}, ready: bool}
     */
    public function assess(ResearchSubmission $submission): array
    {
        $template = $submission->template();

        $missingSections = $this->sections->missingRequiredSections($submission, $template);
        $requiredSectionCount = count($template->requiredSectionKeys());

        $uploadedTypes = $submission->documents()->pluck('document_type')->all();
        $requiredAttachments = collect($template->attachments)->where('required', true);
        $missingAttachments = $requiredAttachments
            ->reject(fn ($attachment) => in_array($attachment->key, $uploadedTypes, true))
            ->pluck('label')
            ->values();

        return [
            'sections' => [
                'total' => $requiredSectionCount,
                'done' => $requiredSectionCount - $missingSections->count(),
                'missing' => $missingSections->values()->all(),
            ],
            'attachments' => [
                'total' => $requiredAttachments->count(),
                'done' => $requiredAttachments->count() - $missingAttachments->count(),
                'missing' => $missingAttachments->all(),
            ],
            'ready' => $missingSections->isEmpty() && $missingAttachments->isEmpty(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function errors(ResearchSubmission $submission): array
    {
        $assessment = $this->assess($submission);
        $errors = [];

        if ($assessment['sections']['missing'] !== []) {
            $errors[] = 'Missing content for: '.implode(', ', $assessment['sections']['missing']);
        }

        if ($assessment['attachments']['missing'] !== []) {
            $errors[] = 'Missing required attachment(s): '.implode(', ', $assessment['attachments']['missing']);
        }

        return $errors;
    }
}
