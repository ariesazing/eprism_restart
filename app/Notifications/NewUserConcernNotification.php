<?php

namespace App\Notifications;

use App\Models\ResearchConcern;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserConcernNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ResearchConcern $concern,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New User Concern Submitted')
            ->greeting("Hello {$notifiable->name},")
            ->line('A new user concern or inquiry has been submitted in ePRISM.')
            ->line('Please review and respond to this inquiry in the administrative portal.')
            ->action('View User Concerns', route('admin.concerns.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_user_concern',
            'concern_id' => $this->concern->id,
            'title' => 'New User Concern Submitted',
            'reference_code' => $this->concern->submission?->reference_code ?? 'General Inquiry',
            'message' => 'A new user concern has been submitted and is awaiting your review.',
            'url' => route('admin.concerns.index'),
        ];
    }
}
