<?php

namespace App\Jobs;

use App\Models\ResearchSubmission;
use App\Services\ApprovedDocumentRetention;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneApprovedDocumentVersions implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120];

    public function __construct(public int $submissionId) {}

    public function handle(ApprovedDocumentRetention $retention): void
    {
        if ($submission = ResearchSubmission::find($this->submissionId)) {
            $retention->prune($submission);
        }
    }
}
