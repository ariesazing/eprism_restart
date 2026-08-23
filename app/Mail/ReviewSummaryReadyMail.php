<?php

namespace App\Mail;

use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReviewSummaryReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ResearchSubmission $submission,
        public readonly RapmDocument $document,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Review Summary Ready: {$this->submission->reference_code}")
            ->view('mail.rapm-document-ready', [
                'documentLabel' => 'Review Summary',
                'downloadUrl' => route('rapm-documents.show', $this->document),
            ]);
    }
}
