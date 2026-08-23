<?php

namespace App\Notifications;

use App\Models\ResearchSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell-icon) record of a decision on the researcher's own submission. Email for the
 * same event is sent directly via a Mailable (SubmissionApprovedMail / SubmissionRevisions
 * RequiredMail) so it can also reach proponents who have no user account — this notification
 * is database-only to avoid double-emailing the researcher.
 */
class SubmissionDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ResearchSubmission $submission,
        public readonly string $title,
        public readonly ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'submission_id' => $this->submission->id,
            'reference_code' => $this->submission->reference_code,
            'url' => $this->url ?? route('submissions.show', $this->submission),
        ];
    }
}
