<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;

class SubmissionDecisionService
{
    /**
     * Recompute the submission's status from its reviewers' current recommendations.
     * Reviewers are the only decision-makers in this workflow: any revision request
     * sends the submission back immediately, and unanimous approval from every
     * assigned reviewer advances it (proposal -> completed, completed -> approved).
     */
    public function evaluate(ResearchSubmission $submission): void
    {
        $reviewerIds = $submission->reviewers()->pluck('users.id');

        if ($reviewerIds->isEmpty()) {
            return;
        }

        $reviews = $submission->reviews()
            ->whereIn('reviewer_id', $reviewerIds)
            ->with('reviewer')
            ->get()
            ->keyBy('reviewer_id');

        $revisionReviews = $reviews->filter(
            fn ($review) => in_array($review->recommendation, ['minor_revision', 'major_revision'], true)
        );

        if ($revisionReviews->isNotEmpty()) {
            $notes = $revisionReviews
                ->map(fn ($review) => $review->reviewer->name.': '.$review->comments)
                ->join("\n\n");

            $submission->update([
                'status' => SubmissionStatus::REVISIONS_REQUIRED,
                'admin_notes' => $notes,
            ]);

            return;
        }

        $allApproved = $reviewerIds->every(
            fn ($reviewerId) => optional($reviews->get($reviewerId))->recommendation === 'approve'
        );

        if (! $allApproved) {
            return;
        }

        if ($submission->classification === 'proposal') {
            $submission->update([
                'classification' => 'completed',
                'status' => SubmissionStatus::DRAFT,
                'admin_notes' => null,
            ]);

            $submission->reviews()->delete();

            return;
        }

        $submission->update([
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
