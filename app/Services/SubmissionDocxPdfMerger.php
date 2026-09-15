<?php

namespace App\Services;

use App\Models\ResearchDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Assembles the final manuscript PDF for an 'onlyoffice'-engine submission from two
 * independently-produced sources, in order: the manuscript PDF (SubmissionDocxComposer::
 * composeManuscript() — front matter *and* every rich_text chapter, spliced into one document
 * before conversion, so it already carries the admin's native Word header/footer/margins on
 * every one of its own pages) and uploaded attachment PDFs, stamped with the letterhead
 * (SubmissionDocxComposer::composeLetterhead() — a blank-body variant of the same admin
 * template, carrying only its real header/footer, used purely as a stamping source, never
 * appended itself) since an attachment is still a separate uploaded file with no header/footer
 * of its own.
 *
 * This is the ONLYOFFICE-era counterpart to SubmissionPdfMerger, which keeps assembling
 * 'canvas_editor'-engine submissions exactly as it does today via HTML-overlay stamping — kept
 * as a genuinely separate class rather than folded in, since the two stamping algorithms have
 * nothing in common (see SubmissionSnapshotService, which picks between the two composers).
 *
 * Chapter navigation markers (the invisible `[[section:<key>]]` text pdf-review.js's
 * wireChapterNav() searches for) are no longer stamped here at the PDF level at all — each
 * chapter now carries its own marker as a real text run inserted directly into the assembled
 * docx, before conversion (see scripts/manuscript.py's assemble_chapters()/chapter_marker()),
 * since a chapter is a native part of the manuscript PDF now rather than a separately-appended
 * page this class used to stamp one.
 *
 * Attachments get the letterhead applied by drawing the blank-body reference's own first page
 * as a full-page background, then drawing the attachment's real content scaled to fit *inside*
 * a fixed content band on top. A first attempt used the *filled* front matter's own first page
 * as that background instead — found live, against a real Document Server, to be wrong: a PDF
 * page has no opaque fill of its own wherever nothing is drawn, so the front matter's own body
 * content (title, proponents, tables) bled through underneath any attachment page that didn't
 * happen to visually cover the exact same area. Using a genuinely blank-body reference removes
 * that failure mode entirely, rather than papering over it with a guessed-margin white
 * rectangle (which is still kept below, now just as a cheap defensive measure against a stray
 * body background style, not load-bearing).
 */
class SubmissionDocxPdfMerger
{
    /**
     * Reserved band (mm) at the top/bottom of every stamped attachment page — leaves room for
     * the letterhead reference's own header/footer to stay visible without the attachment's
     * content visually overlapping it. Approximates a typical Word document's margins; not
     * admin-configurable yet, revisit if a real template's header/footer needs more room.
     */
    private const LETTERHEAD_TOP_MM = 30.0;

    private const LETTERHEAD_BOTTOM_MM = 20.0;

    /**
     * @param  Collection<int, ResearchDocument>  $attachments
     */
    public function merge(string $manuscriptPdf, string $letterheadPdf, Collection $attachments): string
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        // Placing content flush against the reserved bands below would otherwise trip TCPDF's
        // default auto-page-break and silently insert a blank page — same reasoning as
        // SubmissionPdfMerger's identical call.
        $pdf->SetAutoPageBreak(false, 0);

        // The manuscript's own pages already carry the real header/footer natively (front
        // matter and every chapter are the same admin docx, just filled/spliced) — appended
        // unstamped.
        $this->appendSource($pdf, $manuscriptPdf, letterheadTemplateId: null);

        $letterheadTemplateId = $this->importLetterheadTemplate($pdf, $letterheadPdf);

        foreach ($attachments as $attachment) {
            if ($attachment->mime_type !== 'application/pdf' || ! Storage::disk('local')->exists($attachment->path)) {
                continue;
            }

            $this->appendSource($pdf, Storage::disk('local')->get($attachment->path), letterheadTemplateId: $letterheadTemplateId);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Imports the letterhead reference's own first page exactly once — FPDI caches an imported
     * page's data on the Fpdi instance itself once importPage() returns its id, independent of
     * which source file is currently selected or whether it still exists on disk afterward, so
     * every stamped page below reuses this same id via useTemplate() without re-importing.
     */
    private function importLetterheadTemplate(Fpdi $pdf, string $letterheadPdf): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'submission-docx-pdf-letterhead-');
        file_put_contents($tmpPath, $letterheadPdf);

        try {
            $pdf->setSourceFile($tmpPath);

            return $pdf->importPage(1);
        } finally {
            unlink($tmpPath);
        }
    }

    /**
     * Appends every page of $bytes to $pdf. When $letterheadTemplateId is given, each page
     * first gets that template drawn as a full-page background, then the source page itself
     * scaled into the fixed content band on top.
     */
    private function appendSource(Fpdi $pdf, string $bytes, ?string $letterheadTemplateId): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'submission-docx-pdf-');
        file_put_contents($tmpPath, $bytes);

        try {
            $pageCount = $pdf->setSourceFile($tmpPath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage('P', 'A4');
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();

                if ($letterheadTemplateId !== null) {
                    $pdf->useTemplate($letterheadTemplateId, 0, 0, $pageWidth, $pageHeight);
                    [$top, $bottom] = [self::LETTERHEAD_TOP_MM, $pageHeight - self::LETTERHEAD_BOTTOM_MM];

                    // Cheap defensive measure, not load-bearing now that the background is a
                    // genuinely blank-body reference (see class doc) — guards only against an
                    // admin's own body background style/watermark bleeding into the content
                    // band, not against real content (there isn't any left to bleed).
                    $pdf->Rect(0, $top, $pageWidth, $bottom - $top, 'F', [], [255, 255, 255]);
                } else {
                    [$top, $bottom] = [0.0, $pageHeight];
                }

                $availableHeight = $bottom - $top;
                $scale = min($pageWidth / $size['width'], $availableHeight / $size['height']);
                $renderWidth = $size['width'] * $scale;
                $renderHeight = $size['height'] * $scale;
                $x = ($pageWidth - $renderWidth) / 2;
                $y = $top + ($availableHeight - $renderHeight) / 2;

                $pdf->useTemplate($templateId, $x, $y, $renderWidth, $renderHeight);
            }
        } finally {
            unlink($tmpPath);
        }
    }
}
