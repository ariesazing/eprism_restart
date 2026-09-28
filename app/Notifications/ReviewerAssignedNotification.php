<?php

namespace App\Notifications;

use App\Models\ResearchSubmission;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewerAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ResearchSubmission $submission,
        public readonly ?Carbon $deadline = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Assigned to Review: {$this->submission->title} ({$this->submission->reference_code})")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned to evaluate the research submission \"{$this->submission->title}\" ({$this->submission->reference_code}).");

        if ($this->deadline) {
            $mail->line("Evaluation Deadline: {$this->deadline->format('F j, Y g:i A')} ({$this->deadline->diffForHumans()}).");
        }

        return $mail->action('Open Review', route('reviewer.submissions.show', $this->submission))
            ->line('Please review the submission materials and submit your evaluation rubric before the deadline.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reviewer_assigned',
            'submission_id' => $this->submission->id,
            'title' => $this->submission->title,
            'reference_code' => $this->submission->reference_code,
            'deadline' => $this->deadline?->toIso8601String(),
            'message' => "You were assigned to evaluate \"{$this->submission->title}\"",
            'url' => route('reviewer.submissions.show', $this->submission),
        ];
    }
}
