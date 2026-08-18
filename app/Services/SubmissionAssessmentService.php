<?php

namespace App\Services;

use App\Exceptions\GrammarCheckUnavailableException;
use App\Models\ResearchSubmission;
use App\Models\SubmissionReadinessAssessment;

class SubmissionAssessmentService
{
    public function __construct(
        private readonly SubmissionReadinessService $readiness,
        private readonly GrammarCheckService $grammar,
    ) {}

    /**
     * Combine the existing completeness assessment with a grammar-correctness
     * check (Submission Readiness Assessment / "SRAM"), caching the grammar
     * result per content version so unchanged manuscripts don't re-hit
     * LanguageTool on every view.
     *
     * @return array{completeness_percent: int, sections: array{done: int, total: int}, attachments: array{done: int, total: int}, grammar_available: bool, grammar_percent: float|null, word_count: int, issue_count: int, checked_at: string|null}
     */
    public function assess(ResearchSubmission $submission): array
    {
        $completeness = $this->readiness->assess($submission);

        $sectionsTotal = $completeness['sections']['total'];
        $attachmentsTotal = $completeness['attachments']['total'];
        $doneTotal = $sectionsTotal + $attachmentsTotal;
        $completenessPercent = $doneTotal > 0
            ? (int) round((($completeness['sections']['done'] + $completeness['attachments']['done']) / $doneTotal) * 100)
            : 100;

        $plainText = $this->plainText($submission);
        $contentHash = hash('sha256', $plainText);
        $wordCount = $plainText === '' ? 0 : str_word_count($plainText);

        $existing = $submission->readinessAssessment;

        $grammarAvailable = false;
        $grammarPercent = null;
        $issueCount = $existing?->issue_count ?? 0;

        if ($existing && $existing->content_hash === $contentHash && $existing->grammar_percent !== null) {
            $grammarAvailable = true;
            $grammarPercent = (float) $existing->grammar_percent;
            $wordCount = $existing->word_count;
            $issueCount = $existing->issue_count;
        } elseif ($wordCount > 0) {
            try {
                $matches = $this->grammar->check($plainText);
                $issueCount = count($matches);
                $grammarPercent = max(0, round(100 - ($issueCount / $wordCount * 100), 1));
                $grammarAvailable = true;
            } catch (GrammarCheckUnavailableException) {
                if ($existing && $existing->content_hash === $contentHash) {
                    $grammarAvailable = $existing->grammar_percent !== null;
                    $grammarPercent = $existing->grammar_percent !== null ? (float) $existing->grammar_percent : null;
                    $wordCount = $existing->word_count;
                    $issueCount = $existing->issue_count;
                }
            }
        }

        $assessment = SubmissionReadinessAssessment::updateOrCreate(
            ['research_submission_id' => $submission->id],
            [
                'content_hash' => $contentHash,
                'completeness_percent' => $completenessPercent,
                'sections_done' => $completeness['sections']['done'],
                'sections_total' => $sectionsTotal,
                'attachments_done' => $completeness['attachments']['done'],
                'attachments_total' => $attachmentsTotal,
                'grammar_percent' => $grammarPercent,
                'word_count' => $wordCount,
                'issue_count' => $issueCount,
                'checked_at' => now(),
            ]
        );

        return [
            'completeness_percent' => $completenessPercent,
            'sections' => ['done' => $completeness['sections']['done'], 'total' => $sectionsTotal],
            'attachments' => ['done' => $completeness['attachments']['done'], 'total' => $attachmentsTotal],
            'grammar_available' => $grammarAvailable,
            'grammar_percent' => $grammarPercent,
            'word_count' => $wordCount,
            'issue_count' => $issueCount,
            'checked_at' => $assessment->checked_at?->toIso8601String(),
        ];
    }

    private function plainText(ResearchSubmission $submission): string
    {
        return $submission->sections
            ->reject(fn ($section) => $section->isTable())
            ->map(fn ($section) => trim((string) strip_tags((string) $section->content_html)))
            ->filter(fn ($text) => $text !== '')
            ->implode("\n\n");
    }
}
