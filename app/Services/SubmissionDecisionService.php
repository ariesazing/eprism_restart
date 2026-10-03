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
     * A round completes only after every assigned reviewer submits. The mean score
     * decides rejection; passing rounds can still request revision.
     */
    public function evaluate(ResearchSubmission $submission, ?User $causer = null): void
    {
        if (in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::APPROVED], true)
            || ($submission->status === SubmissionStatus::REVISIONS_REQUIRED && !empty($submission->evaluation_results))) {
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

        // Draft comment rows and reviewers who have not submitted do not count.
        if (!$reviewerIds->every(fn ($id) => $reviews->get($id)?->submitted_at !== null)) {
            return;
        }

        $average = EvaluationOutcome::average($reviews);
        $outcome = EvaluationOutcome::result($reviews);
        $numbers = $submission->reviewerNumbers();
        $notes = 'Average score: '.number_format($average, 2)."%.\n\n".$reviews
            ->map(fn ($review) => 'Reviewer '.($numbers[$review->reviewer_id] ?? '?').': '.number_format($review->percentageScore(), 2).'% - '.$review->comments)
            ->join("\n\n");
        $history = $submission->evaluation_results ?? [];
        $history[] = [
            'classification' => $submission->classification,
            'outcome' => $outcome,
            'average' => $average,
            'completed_at' => now()->toIso8601String(),
            'reviews' => $reviews->map(fn ($review) => [
                'reviewer_number' => $numbers[$review->reviewer_id] ?? '?',
                'score' => $review->percentageScore(),
                'result' => $review->percentageScore() < ResearchEvaluationRubric::PASSING_SCORE ? 'rejected' : $review->recommendation,
                'comments' => $review->comments,
                'criteria_scores' => $review->criteria_scores,
                'rubric_key' => $review->rubric_key,
            ])->values()->all(),
        ];
        $submission->update(['evaluation_results' => $history, 'admin_notes' => $notes]);

        if ($causer !== null) {
            $this->reviewSummary->maybeGenerate($submission, $reviews, $causer);
        }

        if ($outcome !== 'approved') {
            // The completed round is rejected/requires revision; the research is returned
            // to the editable revision state and can be submitted for a fresh round.
            $submission->update(['status' => SubmissionStatus::REVISIONS_REQUIRED]);
            $this->activity->log($causer, 'submission.revisions_required', $submission, 'Evaluation completed: '.$outcome.'; average '.number_format($average, 2).'%.');
            $mail = $outcome === 'rejected'
                ? new \App\Mail\EvaluationRejectedMail($submission)
                : new SubmissionRevisionsRequiredMail($submission);
            $this->notifyDecision($submission, $mail, $outcome === 'rejected' ? 'Evaluation rejected - research returned for revision' : 'Revisions requested');
            \Illuminate\Support\Facades\DB::afterCommit(fn () => event(new SubmissionActivity($submission, 'revisions_required', $reviewerIds->all())));
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
            \Illuminate\Support\Facades\DB::afterCommit(fn () => event(new SubmissionActivity($submission, 'promoted_to_completed', $reviewerIds->all())));

            return;
        }

        $submission->update([
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);
        PruneApprovedDocumentVersions::dispatch($submission->id)->afterCommit();

        $this->activity->log($causer, 'submission.approved', $submission, "\"{$submission->title}\" ({$submission->reference_code}) approved and published to the repository.");

        $this->notifyDecision($submission, new SubmissionApprovedMail($submission, isFinal: true), 'Research approved');
        \Illuminate\Support\Facades\DB::afterCommit(fn () => event(new SubmissionActivity($submission, 'approved', $reviewerIds->all())));

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
