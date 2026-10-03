<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Events\SubmissionActivity;
use App\Jobs\EncryptApprovedManuscript;
use App\Jobs\PruneApprovedDocumentVersions;
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
        if ($submission->status === SubmissionStatus::REJECTED) {
            return;
        }

        $reviewerIds = $submission->reviewers()->pluck('users.id');

        if ($reviewerIds->isEmpty()) {
            return;
        }

        $reviews = $submission->reviews()
            ->whereIn('reviewer_id', $reviewerIds)
            ->with('reviewer')
            ->get()
            ->keyBy('reviewer_id');

        // A submitted rejection is final, even if other reviewers have not finished.
        $rejections = $reviews->filter(fn ($review) => $review->submitted_at !== null && $review->recommendation === 'reject');
        if ($rejections->isNotEmpty()) {
            $numbers = $submission->reviewerNumbers();
            $notes = $rejections->map(fn ($review) => 'Reviewer '.($numbers[$review->reviewer_id] ?? '?').': '.$review->comments)->join("\n\n");
            $submission->update([
                'status' => SubmissionStatus::REJECTED,
                'admin_notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $this->activity->log($causer, 'submission.rejected', $submission, 'Research rejected: '.$submission->reference_code);
            $this->notifyDecision($submission, new \App\Mail\SubmissionRejectedMail($submission), 'Research rejected');
            event(new SubmissionActivity($submission, 'rejected', $reviewerIds->all()));
            return;
        }

        // Generating the Review Summary only depends on every reviewer having finished —
        // not on what they recommended — so this runs before the outcome branching below
        // even touches it, and fires the same way whether this round ends in a revision
        // request or unanimous approval.
        if ($causer !== null && $reviewerIds->every(fn ($reviewerId) => $reviews->has($reviewerId))) {
            $this->reviewSummary->maybeGenerate($submission, $reviews, $causer);
        }

        $revisionReviews = $reviews->filter(
            fn ($review) => in_array($review->recommendation, ['revision', 'minor_revision', 'major_revision'], true)
        );

        if ($revisionReviews->isNotEmpty()) {
            // "Reviewer N" rather than the reviewer's real name — this note is shown
            // straight to the researcher (revision-required email, submission show pages),
            // so it must stay blind the same way the Review Summary document does.
            $reviewerNumbers = $submission->reviewerNumbers();

            $notes = $revisionReviews
                ->map(fn ($review) => 'Reviewer '.($reviewerNumbers[$review->reviewer_id] ?? '?').': '.$review->comments)
                ->join("\n\n");

            $submission->update([
                'status' => SubmissionStatus::REVISIONS_REQUIRED,
                'admin_notes' => $notes,
            ]);

            $this->activity->log($causer, 'submission.revisions_required', $submission, "\"{$submission->title}\" ({$submission->reference_code}) sent back for revisions.");

            $this->notifyDecision($submission, new SubmissionRevisionsRequiredMail($submission), 'Revisions requested');
            event(new SubmissionActivity($submission, 'revisions_required', $reviewerIds->all()));

            return;
        }

        $allApproved = $reviewerIds->every(
            fn ($reviewerId) => ($review = $reviews->get($reviewerId))
                && $review->submitted_at !== null
                && $review->recommendation === 'approve'
                && $review->totalScore() >= ResearchEvaluationRubric::PASSING_SCORE
        );

        if (! $allApproved) {
            return;
        }

        if ($submission->usesManuscript()) {
            $version = $submission->currentManuscriptVersion();
            if ($version && ! $version->approved_at) {
                $version->update(['approved_at' => now()]);
                EncryptApprovedManuscript::dispatch($version->id)->afterCommit();
            }
        }

        if ($submission->classification === 'proposal') {
            $submission->update([
                'classification' => 'completed',
                'status' => SubmissionStatus::DRAFT,
                'admin_notes' => null,
                'proposal_approved_at' => now(),
            ]);

            // document_comments.review_id is nullOnDelete() (not cascadeOnDelete()), so this
            // clears the stale review records without also wiping every reviewer comment
            // ever left on the manuscript — see DocumentComment::scopeVisibleToResearcher()
            // and the migration that changed the FK for the full reasoning.
            $submission->reviews()->delete();
            $submission->reviewers()->detach();

            $this->activity->log($causer, 'submission.promoted_to_completed', $submission, "\"{$submission->title}\" ({$submission->reference_code}) approved as a proposal and promoted to completed research. Reviewer assignments cleared — reassign before this can be reviewed.");

            $this->notifyDecision($submission, new SubmissionApprovedMail($submission, isFinal: false), 'Proposal approved');
            event(new SubmissionActivity($submission, 'promoted_to_completed', $reviewerIds->all()));

            return;
        }

        $submission->update([
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);
        PruneApprovedDocumentVersions::dispatch($submission->id)->afterCommit();

        $this->activity->log($causer, 'submission.approved', $submission, "\"{$submission->title}\" ({$submission->reference_code}) approved and published to the repository.");

        $this->notifyDecision($submission, new SubmissionApprovedMail($submission, isFinal: true), 'Research approved');
        event(new SubmissionActivity($submission, 'approved', $reviewerIds->all()));

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
            Mail::to($email)->send($mail->afterCommit());
        }

        $submission->researcher?->notify((new SubmissionDecisionNotification($submission, $notificationTitle))->afterCommit());
    }
}
