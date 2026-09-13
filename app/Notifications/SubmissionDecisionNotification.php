<?php

namespace App\Notifications;

use App\Models\ResearchSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell-icon) record of a decision on the researcher's own submission. Email for the
 * same event is sent directly via a Mailable (SubmissionApprovedMail / SubmissionRevisions
 * RequiredMail) so it can also reach proponents who have no user account — the database
 * channel avoids double-emailing the researcher. The broadcast channel is purely a live-
 * update signal for the topbar bell (see resources/js/live-refresh.js) — it defaults to
 * Laravel's own `App.Models.User.{id}` private channel, already defined in
 * routes/channels.php — so the bell can refetch itself instead of waiting for a reload.
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
        return ['database', 'broadcast'];
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

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
