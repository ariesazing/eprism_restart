<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Rapm\RapmTemplateRegistry;
use Illuminate\Support\Collection;

/**
 * Shapes review/routing data into the scalar/each vocabulary RapmTemplateRenderer (via
 * PlaceholderEngine) expects — the RAPM equivalent of how SubmissionHtmlTemplateRenderer
 * builds its own scalars/each straight from a submission's chapters and proponents.
 */
class RapmDataBuilder
{
    private const RECOMMENDATION_LABELS = [
        'approve' => 'Approve',
        'revision' => 'Revision',
        'minor_revision' => 'Revision',
        'major_revision' => 'Revision',
    ];

    private const RESEARCH_TYPE_LABELS = ['basic' => 'Basic Research', 'action' => 'Action Research'];

    private const CLASSIFICATION_LABELS = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];

    /**
     * @param  Collection<int, Review>  $reviews  Keyed by reviewer_id, as built in SubmissionDecisionService::evaluate().
     * @param  bool  $revealReviewers  false (the default) is the researcher's blind-review copy; true is the admin copy, which also names each reviewer.
     * @return array{scalars: array<string, array{value: string, raw: bool}>, each: array<string, array<int, array<string, string>>>}
     */
    public function buildReviewSummaryData(ResearchSubmission $submission, Collection $reviews, bool $revealReviewers = false): array
    {
        $submission->loadMissing('researcher');

        $hasRevisionRequest = $reviews->contains(
            fn (Review $review) => in_array($review->recommendation, ['revision', 'minor_revision', 'major_revision'], true)
        );

        $reviewerNumbers = $submission->reviewerNumbers();

        // "Reviewer N" rather than the reviewer's real name — blind review: a researcher
        // reading this document must not be able to identify who scored them. The admin copy
        // keeps that same "Reviewer N" (so it can be cross-referenced with what the researcher
        // was sent) and adds the real name after it.
        $reviewerLabel = function (Review $review) use ($reviewerNumbers, $revealReviewers) {
            $label = isset($reviewerNumbers[$review->reviewer_id]) ? 'Reviewer '.$reviewerNumbers[$review->reviewer_id] : '';

            if (! $revealReviewers || $review->loadMissing('reviewer')->reviewer === null) {
                return $label;
            }

            return $label === '' ? $review->reviewer->name : "{$label} ({$review->reviewer->name})";
        };

        $reviewerRows = $reviews->map(fn (Review $review) => [
            'reviewer_name' => $reviewerLabel($review),
            'total_score' => (string) $review->totalScore(),
            'recommendation_label' => self::RECOMMENDATION_LABELS[$review->recommendation] ?? $review->recommendation,
            'comments' => $review->comments ?? '',
            'submitted_at' => $review->submitted_at?->format('F j, Y g:i A') ?? '',
        ])->values()->all();

        // One row per (reviewer, scored criterion) rather than one named column per criterion —
        // see RapmTemplateRegistry's own comment on why: the criteria themselves differ by
        // which of the four scoring rubrics this submission was reviewed against. A review
        // predating rubric_key (rubric() returns null) simply contributes no criteria rows —
        // its reviewer-summary row above still renders.
        $criteriaRows = $reviews->flatMap(function (Review $review) use ($reviewerLabel) {
            $rubric = $review->rubric();

            if ($rubric === null) {
                return [];
            }

            $label = $reviewerLabel($review);
            $scores = $review->criteria_scores ?? [];

            return collect($rubric->sections)->flatMap(fn ($section) => collect($section->leaves())->map(fn ($item) => [
                'reviewer_name' => $label,
                'section_label' => $section->label ?? '',
                'item_code' => $item->code,
                'item_label' => $item->label,
                'score' => (string) ($scores[$item->key] ?? 0),
                'max' => (string) $item->max,
            ]));
        })->values()->all();

        $scalars = [
            'title' => $this->scalar($submission->title),
            'reference_code' => $this->scalar($submission->reference_code ?? ''),
            'research_type_label' => $this->scalar(self::RESEARCH_TYPE_LABELS[$submission->research_type] ?? $submission->research_type),
            'classification_label' => $this->scalar(self::CLASSIFICATION_LABELS[$submission->classification] ?? $submission->classification),
            'organizational_unit' => $this->scalar($submission->organizational_unit ?? ''),
            'researcher_name' => $this->scalar($submission->researcher?->name ?? ''),
            'overall_recommendation_label' => $this->scalar($hasRevisionRequest ? 'Revisions Required' : 'Approved'),
            'admin_notes' => $this->scalar($submission->admin_notes ?? ''),
            'template_label' => $this->scalar(RapmTemplateRegistry::for(RapmDocument::KIND_REVIEW_SUMMARY)->label),
            'generated_at' => $this->scalar(now()->format('F j, Y g:i A')),
        ];

        return ['scalars' => $scalars, 'each' => ['reviewers' => $reviewerRows, 'criteria' => $criteriaRows]];
    }

    /**
     * @return array{scalars: array<string, array{value: string, raw: bool}>, each: array<string, array<int, array<string, string>>>}
     */
    public function buildRoutingSlipData(ResearchSubmission $submission): array
    {
        $submission->loadMissing('researcher');

        $steps = ActivityLog::query()
            ->where('subject_type', $submission->getMorphClass())
            ->where('subject_id', $submission->id)
            ->with('causer')
            ->oldest()
            ->get()
            ->map(fn (ActivityLog $log) => [
                'step_date' => $log->created_at?->format('F j, Y g:i A') ?? '',
                'actor_name' => $log->causer?->name ?? 'System',
                'action_label' => str($log->action)->replace('.', ' ')->replace('_', ' ')->headline()->toString(),
                'description' => $log->description,
            ])
            ->values()
            ->all();

        $scalars = [
            'title' => $this->scalar($submission->title),
            'reference_code' => $this->scalar($submission->reference_code ?? ''),
            'researcher_name' => $this->scalar($submission->researcher?->name ?? ''),
            'organizational_unit' => $this->scalar($submission->organizational_unit ?? ''),
            'submitted_at' => $this->scalar($submission->created_at?->format('F j, Y g:i A') ?? ''),
            'approved_at' => $this->scalar($submission->approved_at?->format('F j, Y g:i A') ?? ''),
            'current_status_label' => $this->scalar($submission->status->label()),
            'template_label' => $this->scalar(RapmTemplateRegistry::for(RapmDocument::KIND_ROUTING_SLIP)->label),
            'generated_at' => $this->scalar(now()->format('F j, Y g:i A')),
        ];

        return ['scalars' => $scalars, 'each' => ['routing_steps' => $steps]];
    }

    private function scalar(string $value): array
    {
        return ['value' => $value, 'raw' => false];
    }
}
