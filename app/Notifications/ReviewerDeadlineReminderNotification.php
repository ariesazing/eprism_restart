<?php

namespace App\Notifications;

use App\Models\ResearchSubmission;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewerDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ResearchSubmission $submission,
        public readonly int $hoursLeft,
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
        $timeLabel = $this->hoursLeft <= 12 ? '12 hours' : '24 hours';

        return (new MailMessage)
            ->subject("Reminder: {$timeLabel} remaining for review of {$this->submission->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is an urgent reminder that your evaluation for \"{$this->submission->title}\" ({$this->submission->reference_code}) is due in approximately {$timeLabel}.")
            ->line($this->deadline ? "Deadline: {$this->deadline->format('F j, Y g:i A')}." : '')
            ->action('Complete Evaluation', route('reviewer.submissions.show', $this->submission))
            ->line('Please finalize your review rubric and comments as soon as possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reviewer_deadline_reminder',
            'submission_id' => $this->submission->id,
            'title' => $this->submission->title,
            'reference_code' => $this->submission->reference_code,
            'hours_left' => $this->hoursLeft,
            'deadline' => $this->deadline?->toIso8601String(),
            'message' => "Reminder: {$this->hoursLeft} hours remaining to evaluate \"{$this->submission->title}\"",
            'url' => route('reviewer.submissions.show', $this->submission),
        ];
    }
}
