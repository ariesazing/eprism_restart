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
     * @return array{sections: array{total: int, done: int, missing: array<int, array{key: string, label: string}>}, attachments: array{total: int, done: int, missing: array<int, string>}, ready: bool, unavailable: bool, error: ?string}
     */
    public function assess(ResearchSubmission $submission): array
    {
        $template = $submission->template();

        $report = $submission->usesManuscript() ? app(ManuscriptService::class)->report($submission) : null;

        // A manuscript-engine report() that couldn't actually run (see its own doc comment)
        // comes back with an empty 'sections' — treating that the normal way below would make
        // every required section look missing, a false "you have no content" rather than the
        // true "we couldn't check right now". Kept out of the section-completeness math
        // entirely instead, and surfaced as its own explicit signal.
        $unavailable = $report !== null && ! ($report['available'] ?? true);

        $missingSections = match (true) {
            $unavailable => collect(),
            $report !== null => collect($template->sections)->filter(fn ($section) => $section->required && blank($report['sections'][$section->key] ?? null))
                ->map(fn ($section) => ['key' => $section->key, 'label' => $section->label]),
            default => $this->sections->missingRequiredSections($submission, $template),
        };
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
                'done' => $unavailable ? 0 : $requiredSectionCount - $missingSections->count(),
                'missing' => $missingSections->values()->all(),
            ],
            'attachments' => [
                'total' => $requiredAttachments->count(),
                'done' => $requiredAttachments->count() - $missingAttachments->count(),
                'missing' => $missingAttachments->all(),
            ],
            // Unavailable never reads as "ready" — a check that couldn't run is not the same
            // as a check that passed — but it's also not reported as the researcher's own
            // incompleteness (see 'unavailable' below, and missingSections staying empty above).
            'ready' => ! $unavailable && $missingSections->isEmpty() && $missingAttachments->isEmpty(),
            'unavailable' => $unavailable,
            'error' => $unavailable ? ($report['error'] ?? null) : null,
        ];
    }
}
