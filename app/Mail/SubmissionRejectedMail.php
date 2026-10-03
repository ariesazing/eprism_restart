<?php

namespace App\Mail;

use App\Models\ResearchSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ResearchSubmission $submission) {}

    public function build(): self
    {
        return $this->subject("Research Rejected: {$this->submission->reference_code}")
            ->view('mail.submission-rejected');
    }
}
