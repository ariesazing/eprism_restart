<?php

namespace App\Mail;

use App\Models\ResearchSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ResearchSubmission $submission,
        public readonly bool $isFinal,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->isFinal
                ? "Research Approved: {$this->submission->reference_code}"
                : "Proposal Approved: {$this->submission->reference_code}")
            ->view('mail.submission-approved');
    }
}
