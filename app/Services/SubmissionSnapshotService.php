<?php

namespace App\Services;

use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class SubmissionSnapshotService
{
    public function __construct(
        private readonly SubmissionPdfComposer $composer,
        private readonly SubmissionPdfMerger $merger,
    ) {}

    /**
     * Compose the structured content + required attachments into one PDF, encrypt it at rest,
     * and record it as the new canonical (read-only) snapshot for this submission.
     */
    public function generate(ResearchSubmission $submission, User $generatedBy): ResearchSnapshot
    {
        $merged = $this->composePreview($submission);

        $version = ($submission->latestSnapshot()?->version ?? 0) + 1;
        $path = "research-snapshots/{$submission->id}/v{$version}.pdf.enc";

        Storage::disk('local')->put($path, Crypt::encrypt($merged));

        return $submission->snapshots()->create([
            'version' => $version,
            'path' => $path,
            'generated_by' => $generatedBy->id,
            'generated_at' => now(),
        ]);
    }

    /**
     * Compose the current (possibly still-being-edited) content + attachments into one PDF
     * without persisting anything — used to let a researcher preview a draft's manuscript
     * before it's ever been submitted, when no immutable snapshot exists yet.
     */
    public function composePreview(ResearchSubmission $submission): string
    {
        $template = $submission->template();

        // Resolved once and reused: composeHeaderFooterOverlay() drives real (measurement-
        // based, non-trivial) work, and compose() needs the exact same header/footer +
        // geometry the merger below stamps onto attachment pages — computing it twice would
        // both double that cost and risk the two ending up subtly different.
        $overlay = $this->composer->composeHeaderFooterOverlay($submission);
        $contentPdf = $this->composer->compose($submission, $overlay);

        $attachments = $submission->documents()
            ->whereIn('document_type', $template->attachmentKeys())
            ->get();

        return $this->merger->merge($contentPdf, $attachments, $overlay);
    }

    public function decryptedBytes(ResearchSnapshot $snapshot): string
    {
        return Crypt::decrypt(Storage::disk('local')->get($snapshot->path));
    }
}
