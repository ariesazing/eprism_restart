<?php

namespace App\Services;

use App\Exceptions\GrammarCheckUnavailableException;
use App\Models\ResearchSubmission;
use App\Models\SubmissionReadinessAssessment;
use App\Similarity\TextExtractor;

class SubmissionAssessmentService
{
    public function __construct(
        private readonly SubmissionReadinessService $readiness,
        private readonly GrammarCheckService $grammar,
        private readonly TextExtractor $extractor,
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

        $flaggedChapters = [];
        $chapterMetrics = [];

        if ($submission->usesManuscript()) {
            $path = $submission->manuscript['working_path'] ?? null;
            $paragraphs = $path ? ($this->extractor->docxParagraphs($path) ?? []) : [];
            $text = trim(implode(' ', $paragraphs));
            $secWordCount = $text === '' ? 0 : str_word_count($text);
            $isFlagged = $secWordCount < 10;
            $reason = $isFlagged ? "Manuscript contains less than 10 words (found {$secWordCount} " . ($secWordCount === 1 ? 'word' : 'words') . ')' : null;

            if ($isFlagged) {
                $flaggedChapters[] = [
                    'key' => 'manuscript',
                    'label' => 'Manuscript Document',
                    'word_count' => $secWordCount,
                    'reason' => $reason,
                ];
            }

            $chapterMetrics[] = [
                'key' => 'manuscript',
                'label' => 'Manuscript Document',
                'type' => 'manuscript',
                'word_count' => $secWordCount,
                'is_table' => false,
                'status' => $isFlagged ? 'flagged' : 'ready',
                'reason' => $reason,
            ];
        } else {
            $sections = $submission->relationLoaded('sections') ? $submission->sections : $submission->sections()->orderBy('sort_order')->get();

            foreach ($sections as $section) {
                if ($section->isTable()) {
                    $chapterMetrics[] = [
                        'key' => $section->section_key,
                        'label' => $section->label ?: ucfirst(str_replace('_', ' ', $section->section_key)),
                        'type' => 'table',
                        'word_count' => null,
                        'is_table' => true,
                        'status' => 'ready',
                        'reason' => null,
                    ];
                    continue;
                }

                $paragraphs = $this->extractor->sectionParagraphs($section);
                $text = trim(implode(' ', $paragraphs));
                $secWordCount = $text === '' ? 0 : str_word_count($text);
                $isFlagged = $secWordCount < 10;
                $reason = $isFlagged ? "Chapter contains less than 10 words (found {$secWordCount} " . ($secWordCount === 1 ? 'word' : 'words') . ')' : null;

                if ($isFlagged) {
                    $flaggedChapters[] = [
                        'key' => $section->section_key,
                        'label' => $section->label ?: ucfirst(str_replace('_', ' ', $section->section_key)),
                        'word_count' => $secWordCount,
                        'reason' => $reason,
                    ];
                }

                $chapterMetrics[] = [
                    'key' => $section->section_key,
                    'label' => $section->label ?: ucfirst(str_replace('_', ' ', $section->section_key)),
                    'type' => $section->type ?: 'text',
                    'word_count' => $secWordCount,
                    'is_table' => false,
                    'status' => $isFlagged ? 'flagged' : 'ready',
                    'reason' => $reason,
                ];
            }
        }

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

        $metrics = [
            'completeness_percent' => $completenessPercent,
            'grammar_percent' => $grammarPercent,
            'word_count' => $wordCount,
            'issue_count' => $issueCount,
            'flagged_count' => count($flaggedChapters),
            'flagged_chapters' => $flaggedChapters,
            'chapter_metrics' => $chapterMetrics,
            'sections' => ['done' => $completeness['sections']['done'], 'total' => $sectionsTotal],
            'attachments' => ['done' => $completeness['attachments']['done'], 'total' => $attachmentsTotal],
        ];

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
                'metrics' => $metrics,
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
            'flagged_chapters' => $flaggedChapters,
            'metrics' => $metrics,
        ];
    }

    /**
     * Every prose chapter's text, for the grammar score. Read through TextExtractor rather than
     * content_html directly: for an ONLYOFFICE chapter content_html is only a best-effort mirror of
     * its saved .docx (refreshed by a conversion that can fail or lag — see
     * SubmissionSectionService::sectionHasNoContent()), so reading it made a chapter the researcher
     * had genuinely written score as empty. A canvas-editor chapter has no .docx and still reads its
     * content_html, exactly as before.
     */
    private function plainText(ResearchSubmission $submission): string
    {
        if ($submission->usesManuscript()) {
            $path = $submission->manuscript['working_path'] ?? null;
            $paragraphs = $path ? ($this->extractor->docxParagraphs($path) ?? []) : [];
            return trim(implode("\n\n", $paragraphs));
        }

        $sections = $submission->relationLoaded('sections') ? $submission->sections : $submission->sections()->orderBy('sort_order')->get();

        return $sections
            ->reject(fn ($section) => $section->isTable())
            ->map(fn ($section) => trim(implode("\n", $this->extractor->sectionParagraphs($section))))
            ->filter(fn ($text) => $text !== '')
            ->implode("\n\n");
    }
}
