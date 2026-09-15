<?php

namespace App\Jobs;

use App\Models\ResearchSubmission;
use App\Services\ManuscriptProcessor;
use App\Services\ManuscriptService;
use App\Services\OnlyOfficeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Off-request half of ManuscriptPreviewService: converts the docx+attachments requestPreview()
 * already captured into a merged PDF, and publishes it. Never touches the working copy or any
 * manuscript_versions row — a failure here only affects the disposable preview, never the
 * submission's real content.
 */
class ComposeManuscriptPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public array $backoff = [15];

    public function __construct(
        public int $submissionId,
        public string $sourceFolder,
        public string $sourceHash,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('manuscript-preview-'.$this->submissionId))->releaseAfter(15)->expireAfter(330)];
    }

    public function handle(OnlyOfficeService $office, ManuscriptProcessor $processor, ManuscriptService $files): void
    {
        $disk = Storage::disk('local');

        try {
            $docxPath = $this->sourceFolder.'/source.docx';

            if (! $disk->exists($docxPath)) {
                throw new RuntimeException('The captured preview source is missing.');
            }

            $attachments = collect($disk->files($this->sourceFolder))
                ->filter(fn ($path) => str_contains($path, '/attachment-'))
                ->map(fn ($path) => $disk->path($path))
                ->values()->all();

            $bytes = $office->convertFilledDocxToPdf($disk->get($docxPath), false);
            $files->put($this->sourceFolder.'/source.pdf', $bytes);

            $output = $this->sourceFolder.'/preview.pdf';
            $processor->run('merge_pdf', [
                'input' => $disk->path($this->sourceFolder.'/source.pdf'),
                'output' => $disk->path($output),
                'attachments' => $attachments,
            ]);

            $merged = $disk->get($output);

            if (! str_starts_with((string) $merged, '%PDF-')) {
                throw new RuntimeException('The converter did not produce a PDF.');
            }

            $path = 'manuscripts/'.$this->submissionId.'/preview.pdf.enc';
            $files->put($path, Crypt::encrypt($merged));

            $this->publish('ready', $path, null);
        } finally {
            $disk->deleteDirectory($this->sourceFolder);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception ?? new RuntimeException('Manuscript preview generation failed.'));

        $this->publish('failed', null, 'The preview could not be generated. Try again, or contact an administrator if this keeps happening.');

        Storage::disk('local')->deleteDirectory($this->sourceFolder);
    }

    /**
     * Only writes if this job's source_hash still matches what's on the submission — a newer
     * requestPreview() call may have superseded this one while it was running, and a stale job
     * finishing late must never overwrite a fresher request's own in-flight/ready state.
     */
    private function publish(string $state, ?string $path, ?string $error): void
    {
        DB::transaction(function () use ($state, $path, $error) {
            $submission = ResearchSubmission::whereKey($this->submissionId)->lockForUpdate()->first();

            if (! $submission) {
                return;
            }

            $current = $submission->manuscript ?? [];

            if (($current['preview']['source_hash'] ?? null) !== $this->sourceHash) {
                return;
            }

            $current['preview'] = ['state' => $state, 'source_hash' => $this->sourceHash, 'path' => $path, 'error' => $error];
            $submission->update(['manuscript' => $current]);
        });
    }
}
