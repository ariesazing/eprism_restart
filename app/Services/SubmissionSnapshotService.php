<?php

namespace App\Services;

use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Similarity\TextExtractor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class SubmissionSnapshotService
{
    public function __construct(
        private readonly SubmissionPdfComposer $composer,
        private readonly SubmissionPdfMerger $merger,
        private readonly SubmissionDocxComposer $docxComposer,
    ) {}

    /**
     * Compose the structured content + required attachments into one PDF, encrypt it at rest,
     * and record it as the new canonical (read-only) snapshot for this submission.
     */
    public function generate(ResearchSubmission $submission, User $generatedBy): ResearchSnapshot
    {
        if ($submission->usesManuscript()) {
            throw new \LogicException('Complete manuscripts must be submitted through the version processing queue.');
        }
        $merged = $this->composePreview($submission);

        $version = ($submission->latestSnapshot()?->version ?? 0) + 1;
        $path = "research-snapshots/{$submission->id}/v{$version}.pdf.enc";

        Storage::disk('local')->put($path, Crypt::encrypt($merged));

        return $submission->snapshots()->create([
            'version' => $version,
            'path' => $path,
            'generated_by' => $generatedBy->id,
            'generated_at' => now(),
            'similarity_text' => implode("\n\n", array_column(app(TextExtractor::class)->paragraphs($submission), 'text')),
        ]);
    }

    /**
     * Compose the current (possibly still-being-edited) content + attachments into one PDF
     * without persisting anything — used to let a researcher preview a draft's manuscript
     * before it's ever been submitted, when no immutable snapshot exists yet.
     *
     * HTML drafts use dompdf and chapter drafts use ONLYOFFICE. Both append original
     * attachment pages through the same merger. Complete manuscripts use the queued
     * ManuscriptPreviewService pipeline instead.
     */
    public function composePreview(ResearchSubmission $submission): string
    {
        // usesOnlyOffice() is also true for 'onlyoffice_manuscript' (it covers both onlyoffice
        // engines) — guarded explicitly rather than just omitted, so a caller that forgets to
        // route a manuscript submission to ManuscriptPreviewService instead gets a clear error
        // here rather than silently composing it through the wrong (per-chapter) pipeline,
        // which has no manuscript-shaped sections to read at all.
        if ($submission->usesManuscript()) {
            throw new \LogicException('Complete manuscripts are previewed through ManuscriptPreviewService, not composePreview().');
        }

        return $submission->usesOnlyOffice()
            ? $this->withHeadroomForManyChapterConversions(fn () => $this->composePreviewViaOnlyOffice($submission))
            : $this->composePreviewViaLegacyHtml($submission);
    }

    /**
     * Allow headroom for DOCX assembly, conversion, and raw attachment merging, restoring
     * the request's original execution limit afterward.
     */
    private function withHeadroomForManyChapterConversions(callable $callback): string
    {
        $previous = (int) ini_get('max_execution_time');
        set_time_limit(300);

        try {
            return $callback();
        } finally {
            set_time_limit($previous);
        }
    }

    private function composePreviewViaLegacyHtml(ResearchSubmission $submission): string
    {
        $template = $submission->template();

        // Resolve the generated content letterhead once; attachments remain untouched.
        $overlay = $this->composer->composeHeaderFooterOverlay($submission);
        $contentPdf = $this->composer->compose($submission, $overlay);

        $attachments = $submission->documents()
            ->whereIn('document_type', $template->attachmentKeys())
            ->orderBy('id')
            ->get();

        return $this->merger->merge($contentPdf, $attachments);
    }

    private function composePreviewViaOnlyOffice(ResearchSubmission $submission): string
    {
        $template = $submission->template();
        $manuscriptPdf = $this->docxComposer->composeManuscript($submission);

        $attachments = $submission->documents()
            ->whereIn('document_type', $template->attachmentKeys())
            ->orderBy('id')
            ->get();

        return $this->merger->merge($manuscriptPdf, $attachments);
    }

    public function decryptedBytes(ResearchSnapshot $snapshot): string
    {
        abort_if($snapshot->files_pruned_at, 410, 'This previous version was removed after final approval to save storage.');
        $payload = Storage::disk('local')->get($snapshot->path);

        abort_if($payload === null, 404, 'The snapshot file is missing from storage.');

        return Crypt::decrypt($payload);
    }
}
