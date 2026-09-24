<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionWindowChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $researchType, public string $classification, public bool $open)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = ucfirst($this->researchType).' research '.($this->classification === 'proposal' ? 'proposal' : 'completed research');
        $state = $this->open ? 'open' : 'closed';

        return (new MailMessage)
            ->subject("Submissions {$state}: {$label}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("The submission window for {$label} is now {$state}.")
            ->action('View submission timeline', url('/'));
    }
}
