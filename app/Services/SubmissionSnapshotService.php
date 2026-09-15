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
        private readonly SubmissionDocxComposer $docxComposer,
        private readonly SubmissionDocxPdfMerger $docxMerger,
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
        ]);
    }

    /**
     * Compose the current (possibly still-being-edited) content + attachments into one PDF
     * without persisting anything — used to let a researcher preview a draft's manuscript
     * before it's ever been submitted, when no immutable snapshot exists yet.
     *
     * Branches on the submission's own editor_engine: 'canvas_editor' submissions render
     * exactly as they always have (the HTML/dompdf pipeline is permanent for them — see
     * SubmissionDocxPdfMerger's class doc for why); 'onlyoffice' submissions assemble a
     * front-matter PDF plus each rich_text chapter's own independently-converted PDF plus
     * attachments. 'onlyoffice_manuscript' submissions never reach here at all — that preview
     * is queued/persisted instead of composed synchronously on every request, see
     * ManuscriptPreviewService and ResearchSubmissionController::streamManuscript().
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
     * Assembling an 'onlyoffice'-engine manuscript makes one sequential Document Server round
     * trip per rich_text chapter (front matter + letterhead + one per chapter — a template like
     * action_proposal has 8), each a real docx-to-PDF conversion plus a qpdf normalization pass.
     * PHP's default max_execution_time (60s on many setups) comfortably covers one or two such
     * round trips but not a whole template's worth in sequence — confirmed live, a real
     * "Maximum execution time of 60 seconds exceeded" fatal on exactly this call. Scoped here
     * rather than raised globally, same reasoning as SubmissionPdfComposer's own memory_limit
     * override for its own expensive, infrequent operation.
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

    private function composePreviewViaOnlyOffice(ResearchSubmission $submission): string
    {
        $template = $submission->template();
        $manuscriptPdf = $this->docxComposer->composeManuscript($submission);
        $letterheadPdf = $this->docxComposer->composeLetterhead($submission);

        $attachments = $submission->documents()
            ->whereIn('document_type', $template->attachmentKeys())
            ->get();

        return $this->docxMerger->merge($manuscriptPdf, $letterheadPdf, $attachments);
    }

    public function decryptedBytes(ResearchSnapshot $snapshot): string
    {
        $payload = Storage::disk('local')->get($snapshot->path);

        abort_if($payload === null, 404, 'The snapshot file is missing from storage.');

        return Crypt::decrypt($payload);
    }
}
