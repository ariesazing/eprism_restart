<?php

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Events\SubmissionActivity;
use App\Models\ManuscriptVersion;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ManuscriptProcessor;
use App\Services\ManuscriptService;
use App\Services\OnlyOfficeService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Off-request half of the complete-manuscript submit/resubmit flow: re-verifies the frozen
 * version's own checksums, runs the Python structural validator against it, and — only if
 * valid — converts it to a review PDF, merges in the attachment PDFs, and publishes the result
 * as a new ResearchSnapshot. Dispatched by ManuscriptService::freeze() right after the version
 * row is committed; a second run against an already-terminal (ready/failed) version is a safe
 * no-op, so a queue retry or a duplicate dispatch can never double-process or double-submit.
 */
class ProcessManuscriptVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [15, 60];

    public function __construct(public int $versionId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('manuscript-version-'.$this->versionId))->releaseAfter(15)->expireAfter(660)];
    }

    public function handle(ManuscriptProcessor $processor, OnlyOfficeService $office, ManuscriptService $files): void
    {
        $version = ManuscriptVersion::findOrFail($this->versionId);

        if ($version->state !== 'processing') {
            return;
        }

        $disk = Storage::disk('local');
        $this->verifyChecksum($disk, $version->docx_path, $version->docx_hash);
        $this->verifyChecksum($disk, $version->template_path, $version->template_hash);

        foreach ($version->attachments as $attachment) {
            $this->verifyChecksum($disk, $attachment['path'], $attachment['hash']);
        }

        $result = $processor->run('validate', [
            'input' => $disk->path($version->docx_path),
            'template' => $disk->path($version->template_path),
            'sections' => $version->metadata['sections'] ?? [],
        ]);

        if (! ($result['valid'] ?? false)) {
            $this->publishFailure($version, $result);

            return;
        }

        $reviewPdf = $office->convertFilledDocxToPdf($disk->get($version->docx_path));

        $folder = 'manuscripts/tmp/'.$version->attempt;
        $disk->makeDirectory($folder);

        try {
            $inputPath = $folder.'/review.pdf';
            $outputPath = $folder.'/merged.pdf';
            $files->put($inputPath, $reviewPdf);

            $processor->run('merge_pdf', [
                'input' => $disk->path($inputPath),
                'output' => $disk->path($outputPath),
                'attachments' => collect($version->attachments)->map(fn ($attachment) => $disk->path($attachment['path']))->all(),
            ]);

            $merged = $disk->get($outputPath);

            if (! str_starts_with((string) $merged, '%PDF-')) {
                throw new RuntimeException('The converter did not produce a PDF.');
            }
        } finally {
            $disk->deleteDirectory($folder);
        }

        $this->publishSuccess($version, $result, $merged);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception ?? new RuntimeException('Manuscript version processing failed.'));

        DB::transaction(function () {
            $version = ManuscriptVersion::whereKey($this->versionId)->lockForUpdate()->first();

            if ($version === null || $version->state !== 'processing') {
                return;
            }

            $message = 'The manuscript could not be processed. Try submitting again, or contact an administrator if this keeps happening.';
            $version->update(['state' => 'failed', 'error' => $message]);
            $this->unlockSubmission($version, $message);
        });
    }

    private function verifyChecksum(Filesystem $disk, string $path, string $hash): void
    {
        $bytes = $disk->get($path);

        if ($bytes === null || ! hash_equals($hash, hash('sha256', $bytes))) {
            throw new RuntimeException("A manuscript source at [{$path}] is missing or its checksum has changed.");
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function publishFailure(ManuscriptVersion $version, array $result): void
    {
        DB::transaction(function () use ($version, $result) {
            $locked = ManuscriptVersion::whereKey($version->id)->lockForUpdate()->first();

            if ($locked === null || $locked->state !== 'processing') {
                return;
            }

            $message = 'Your manuscript did not pass automatic validation. Open it to review the issues, fix them, and submit again.';
            $locked->update(['state' => 'failed', 'validation' => $result, 'error' => $message]);
            $this->unlockSubmission($locked, $message);
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function publishSuccess(ManuscriptVersion $version, array $result, string $mergedPdf): void
    {
        DB::transaction(function () use ($version, $result, $mergedPdf) {
            $locked = ManuscriptVersion::whereKey($version->id)->lockForUpdate()->first();

            if ($locked === null || $locked->state !== 'processing') {
                return;
            }

            $submission = ResearchSubmission::whereKey($locked->research_submission_id)->lockForUpdate()->firstOrFail();

            $snapshotVersion = ($submission->latestSnapshot()?->version ?? 0) + 1;
            $path = "research-snapshots/{$submission->id}/v{$snapshotVersion}.pdf.enc";
            Storage::disk('local')->put($path, Crypt::encrypt($mergedPdf));

            $snapshot = $submission->snapshots()->create([
                'version' => $snapshotVersion,
                'path' => $path,
                'generated_by' => $locked->created_by,
                'generated_at' => now(),
            ]);

            $locked->update(['state' => 'ready', 'validation' => $result, 'research_snapshot_id' => $snapshot->id]);

            $wasRevision = ($locked->metadata['previous_status'] ?? null) === SubmissionStatus::REVISIONS_REQUIRED->value;

            $submission->update([
                'status' => $wasRevision ? SubmissionStatus::RESUBMITTED : SubmissionStatus::SUBMITTED,
                'submitted_at' => now(),
                ...($wasRevision ? ['admin_notes' => null] : []),
                'manuscript' => array_merge($submission->manuscript ?? [], ['state' => 'submitted', 'error' => null]),
            ]);

            $causer = User::find($locked->created_by);
            $type = $wasRevision ? 'resubmitted' : 'submitted';

            event(new SubmissionActivity($submission, $type));
            app(ActivityLogger::class)->log(
                $causer,
                "submission.{$type}",
                $submission,
                ($causer?->name ?? 'A researcher')." submitted \"{$submission->title}\" ({$submission->reference_code}) for review."
            );
        });
    }

    private function unlockSubmission(ManuscriptVersion $version, string $message): void
    {
        $submission = ResearchSubmission::whereKey($version->research_submission_id)->lockForUpdate()->first();

        if ($submission === null) {
            return;
        }

        $state = $submission->manuscript ?? [];

        if (($state['version_id'] ?? null) === $version->id) {
            $state['state'] = 'editing';
            $state['error'] = $message;
            $submission->update(['manuscript' => $state]);
        }
    }
}
