<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Mail\SubmissionApprovedMail;
use App\Mail\SubmissionRevisionsRequiredMail;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class SubmissionDecisionService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly RapmReviewSummaryService $reviewSummary,
        private readonly RapmRoutingSlipService $routingSlip,
    ) {}

    /**
     * Recompute the submission's status from its reviewers' current recommendations.
     * Reviewers are the only decision-makers in this workflow: any revision request
     * sends the submission back immediately, and unanimous approval from every
     * assigned reviewer advances it (proposal -> completed, completed -> approved).
     */
    public function evaluate(ResearchSubmission $submission, ?User $causer = null): void
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

        // Generating the Review Summary only depends on every reviewer having finished —
        // not on what they recommended — so this runs before the outcome branching below
        // even touches it, and fires the same way whether this round ends in a revision
        // request or unanimous approval.
        if ($causer !== null && $reviewerIds->every(fn ($reviewerId) => $reviews->has($reviewerId))) {
            $this->reviewSummary->maybeGenerate($submission, $reviews, $causer);
        }

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

            $this->activity->log($causer, 'submission.revisions_required', $submission, "\"{$submission->title}\" ({$submission->reference_code}) sent back for revisions.");

            $this->notifyDecision($submission, new SubmissionRevisionsRequiredMail($submission), 'Revisions requested');

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

            $this->activity->log($causer, 'submission.promoted_to_completed', $submission, "\"{$submission->title}\" ({$submission->reference_code}) approved as a proposal and promoted to completed research.");

            $this->notifyDecision($submission, new SubmissionApprovedMail($submission, isFinal: false), 'Proposal approved');

            return;
        }

        $submission->update([
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $this->activity->log($causer, 'submission.approved', $submission, "\"{$submission->title}\" ({$submission->reference_code}) approved and published to the repository.");

        $this->notifyDecision($submission, new SubmissionApprovedMail($submission, isFinal: true), 'Research approved');

        if ($causer !== null) {
            $this->routingSlip->generate($submission, $causer);
        }
    }

    /**
     * Emails the decision to every proponent (including the researcher, who may double as
     * a proponent) plus a database notification for the researcher's own in-app bell —
     * proponents besides the researcher have no user account, so email is their only channel.
     */
    private function notifyDecision(ResearchSubmission $submission, Mailable $mail, string $notificationTitle): void
    {
        $submission->loadMissing(['researcher', 'proponents']);

        $recipients = collect([$submission->researcher?->email])
            ->merge($submission->proponents->pluck('email'))
            ->filter()
            ->unique()
            ->values();

        // Sent one at a time rather than a single multi-recipient `to()` so co-proponents
        // don't see each other's email addresses in the headers.
        foreach ($recipients as $email) {
            Mail::to($email)->send($mail);
        }

        $submission->researcher?->notify(new SubmissionDecisionNotification($submission, $notificationTitle));
    }
}
