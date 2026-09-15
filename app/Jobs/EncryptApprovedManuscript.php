<?php

namespace App\Jobs;

use App\Models\ManuscriptVersion;
use App\Services\ManuscriptProcessor;
use App\Services\ManuscriptService;
use App\Services\SubmissionSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EncryptApprovedManuscript implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [15, 60];

    public function __construct(public int $versionId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('final-manuscript-'.$this->versionId))->releaseAfter(15)->expireAfter(660)];
    }

    public function handle(ManuscriptProcessor $processor, ManuscriptService $files, SubmissionSnapshotService $snapshots): void
    {
        $version = ManuscriptVersion::findOrFail($this->versionId);
        if (! $version->approved_at || $version->final_pdf_path || ! $version->snapshot) {
            return;
        }
        $disk = Storage::disk('local');
        $folder = 'manuscripts/tmp/'.Str::uuid();
        $disk->makeDirectory($folder);
        try {
            $input = $folder.'/review.pdf';
            $output = $folder.'/final.pdf';
            $files->put($input, $snapshots->decryptedBytes($version->snapshot));
            $password = Str::random(64);
            $processor->run('encrypt_pdf', ['input' => $disk->path($input), 'output' => $disk->path($output), 'owner_password' => $password]);
            $bytes = $disk->get($output);
            $path = 'manuscripts/'.$version->research_submission_id.'/versions/'.$version->attempt.'/final.pdf.enc';
            $files->put($path, Crypt::encrypt($bytes));
            // Clears any stale failure from an earlier attempt (this job is safely retryable —
            // the review PDF it reads from is immutable, so re-running it can only ever produce
            // an equivalent final PDF, never lose or alter the approved content).
            $version->update([
                'final_pdf_path' => $path, 'final_pdf_hash' => hash('sha256', $bytes),
                'final_pdf_owner_password' => $password, 'final_pdf_error' => null,
            ]);
        } finally {
            $disk->deleteDirectory($folder);
        }
    }

    /**
     * Without this, a version that exhausts its retries just silently stays "being prepared"
     * forever (final_pdf_path null, nothing recorded) — the confirmed gap this fixes. Recording
     * the failure here, rather than leaving it to only ever show up in the queue's own failed_jobs
     * table, is what lets ManuscriptController::retryFinalPdf() offer a safe, visible retry
     * instead of requiring a developer to notice and requeue manually.
     */
    public function failed(?Throwable $exception): void
    {
        report($exception ?? new \RuntimeException('Manuscript final PDF encryption failed.'));

        $version = ManuscriptVersion::find($this->versionId);

        if ($version && ! $version->final_pdf_path) {
            $version->update(['final_pdf_error' => 'The final PDF could not be prepared. The approved review PDF is retained — try again, or contact an administrator if this keeps happening.']);
        }
    }
}
