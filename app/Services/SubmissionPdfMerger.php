<?php

namespace App\Services;

use App\Models\ResearchDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class SubmissionPdfMerger
{
    /** CSS px -> mm, matching the 96dpi the composed content PDF's geometry assumes. */
    private const PX_TO_MM = 25.4 / 96;

    /**
     * Append each attachment's PDF pages after the composed content PDF, producing one merged document.
     *
     * The content pages already carry the template's header/footer — dompdf baked them in
     * via template-shell.blade.php. Uploaded attachments are arbitrary pre-existing PDFs
     * with none of that, so $overlay (from SubmissionPdfComposer::composeHeaderFooterOverlay)
     * is stamped onto every attachment page here, using the same geometry as the content
     * pages so the whole merged document reads as one consistent letterheaded document.
     *
     * @param  Collection<int, ResearchDocument>  $attachments
     * @param  array{headerHtml: string, footerHtml: string, geometry: array<string, int>}  $overlay
     */
    public function merge(string $contentPdf, Collection $attachments, array $overlay): string
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        // Placing HTML deliberately close to the page edges below (inside the same
        // print-safe padding the content pages use) would otherwise trip TCPDF's default
        // auto-page-break and silently insert a blank page.
        $pdf->SetAutoPageBreak(false, 0);

        $this->appendSource($pdf, $contentPdf, stampOverlay: false, overlay: $overlay);

        foreach ($attachments as $attachment) {
            if ($attachment->mime_type !== 'application/pdf' || ! Storage::disk('local')->exists($attachment->path)) {
                continue;
            }

            $this->appendSource($pdf, Storage::disk('local')->get($attachment->path), stampOverlay: true, overlay: $overlay);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Every page — whether composed content or an uploaded attachment — is placed on a
     * uniform A4 canvas (matching SubmissionPdfComposer's page size), scaled down to fit
     * and centered if the source page is a different size. Otherwise attachment pages with
     * their own native dimensions would render at a different scale than the generated
     * content when paginated in the manuscript viewer.
     *
     * Attachment pages are additionally scaled to fit *inside* the same header/footer
     * reserved band as the composed pages (rather than the full physical page) — an
     * attachment's own content routinely runs edge-to-edge on its original page, since it
     * was never laid out with our margins in mind, so stamping the header/footer on top of
     * an unshrunk page overlapped whatever was already there.
     *
     * @param  array{headerHtml: string, footerHtml: string, geometry: array<string, int>}  $overlay
     */
    private function appendSource(Fpdi $pdf, string $bytes, bool $stampOverlay, array $overlay): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'submission-pdf-');
        file_put_contents($tmpPath, $bytes);

        try {
            $pageCount = $pdf->setSourceFile($tmpPath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage('P', 'A4');
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();

                if ($stampOverlay) {
                    [$top, $bottom] = $this->contentBand($overlay['geometry'], $pageHeight);
                    // Constrained to the same left/right margins as the content pages
                    // (rather than the full physical page width) so an attachment's visible
                    // edges line up with the manuscript's own text margins instead of
                    // bleeding wider than every content page around it.
                    $marginLeft = $overlay['geometry']['marginLeft'] * self::PX_TO_MM;
                    $marginRight = $overlay['geometry']['marginRight'] * self::PX_TO_MM;
                    $availableWidth = max(1.0, $pageWidth - $marginLeft - $marginRight);
                } else {
                    [$top, $bottom] = [0.0, $pageHeight];
                    $marginLeft = 0.0;
                    $availableWidth = $pageWidth;
                }
                $availableHeight = $bottom - $top;

                $scale = min($availableWidth / $size['width'], $availableHeight / $size['height']);
                $renderWidth = $size['width'] * $scale;
                $renderHeight = $size['height'] * $scale;
                $x = $marginLeft + ($availableWidth - $renderWidth) / 2;
                $y = $top + ($availableHeight - $renderHeight) / 2;

                $pdf->useTemplate($templateId, $x, $y, $renderWidth, $renderHeight);

                if ($stampOverlay) {
                    $this->stampHeaderFooter($pdf, $overlay);
                }
            }
        } finally {
            unlink($tmpPath);
        }
    }

    /**
     * @param  array<string, int>  $geometry
     * @return array{0: float, 1: float} the [top, bottom] mm bounds attachment content must
     *                                   stay within to clear the stamped header/footer.
     */
    private function contentBand(array $geometry, float $pageHeight): array
    {
        $top = $geometry['headerReserve'] * self::PX_TO_MM;
        $bottom = $pageHeight - $geometry['footerReserve'] * self::PX_TO_MM;

        return [$top, $bottom];
    }

    /**
     * @param  array{headerHtml: string, footerHtml: string, geometry: array<string, int>}  $overlay
     */
    private function stampHeaderFooter(Fpdi $pdf, array $overlay): void
    {
        ['headerHtml' => $headerHtml, 'footerHtml' => $footerHtml, 'geometry' => $geometry] = $overlay;

        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();

        $headerHeightMm = $geometry['headerHeight'] * self::PX_TO_MM;
        $footerHeightMm = $geometry['footerHeight'] * self::PX_TO_MM;
        $headerTopMm = $geometry['headerTop'] * self::PX_TO_MM;
        $footerBottomMm = $geometry['footerBottom'] * self::PX_TO_MM;

        $headerMaxImagePt = max(0, $geometry['headerHeight'] - $geometry['imagePadding']) * SubmissionPdfComposer::PX_TO_PT;
        $footerMaxImagePt = max(0, $geometry['footerHeight'] - $geometry['imagePadding']) * SubmissionPdfComposer::PX_TO_PT;

        if (trim($headerHtml) !== '') {
            $pdf->writeHTMLCell($pageWidth, $headerHeightMm, 0, $headerTopMm, $this->constrainImages($headerHtml, $headerMaxImagePt), 0, 0, false, true, 'C');
        }

        if (trim($footerHtml) !== '') {
            $pdf->writeHTMLCell($pageWidth, $footerHeightMm, 0, $pageHeight - $footerBottomMm - $footerHeightMm, $this->constrainImages($footerHtml, $footerMaxImagePt), 0, 0, false, true, 'C');
        }
    }

    /**
     * Rewrites every <img>'s width/height attributes so it renders at most $maxHeightPt
     * tall (aspect ratio preserved), matching template-shell.blade.php's `header img {
     * max-height: ... }` / `footer img { max-height: ... }` rule for the composed content
     * pages. TCPDF's writeHTML doesn't apply a CSS max-height the way dompdf does, so
     * without this an admin's letterhead image — inserted at whatever size it happened to
     * be uploaded — renders at that native size on attachment pages while the content
     * pages correctly shrink it to fit, making the two visibly mismatched.
     *
     * Two separate steps, always both applied: first, unit conversion — a declared/intrinsic
     * width/height is authored in the same 96dpi CSS-px convention dompdf's content pages
     * assume (template-shell.blade.php), but TCPDF's writeHTML interprets a bare <img
     * width/height> as 72dpi points, so every image needs the SubmissionPdfComposer::PX_TO_PT
     * conversion applied regardless of its size — previously this only happened for an image
     * that was *already* oversized (past $maxHeightPt), so any normal-sized letterhead (the
     * common case) was handed to TCPDF unconverted and rendered ~33% too large on attachment
     * pages while looking correct on content pages. Second, the size clamp itself — only
     * applied once the (now correctly pt-converted) height still exceeds $maxHeightPt.
     */
    private function constrainImages(string $html, float $maxHeightPt): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function (array $match) use ($maxHeightPt) {
                $attrs = $match[1];

                $width = $this->attrFloat($attrs, 'width');
                $height = $this->attrFloat($attrs, 'height');

                if ($width === null || $height === null) {
                    $size = $this->intrinsicImageSize($attrs);
                    if ($size === null) {
                        return $match[0];
                    }
                    [$width, $height] = $size;
                }

                $width *= SubmissionPdfComposer::PX_TO_PT;
                $height *= SubmissionPdfComposer::PX_TO_PT;

                if ($maxHeightPt > 0 && $height > $maxHeightPt) {
                    $scale = $maxHeightPt / $height;
                    $width = round($width * $scale, 2);
                    $height = round($maxHeightPt, 2);
                } else {
                    $width = round($width, 2);
                    $height = round($height, 2);
                }

                $attrs = preg_replace(['/\swidth="[^"]*"/i', '/\sheight="[^"]*"/i'], '', $attrs);

                return "<img{$attrs} width=\"{$width}\" height=\"{$height}\">";
            },
            $html
        ) ?? $html;
    }

    private function attrFloat(string $attrs, string $name): ?float
    {
        return preg_match('/\b'.$name.'="(\d+(?:\.\d+)?)"/i', $attrs, $m) === 1 ? (float) $m[1] : null;
    }

    /**
     * @return array{0: float, 1: float}|null [width, height] in pixels, decoded straight
     *                                        from the image bytes when the <img> tag carries
     *                                        no explicit width/height attributes to trust.
     */
    private function intrinsicImageSize(string $attrs): ?array
    {
        if (preg_match('/src="data:image\/[a-zA-Z0-9.+-]+;base64,([^"]+)"/i', $attrs, $m) !== 1) {
            return null;
        }

        $info = @getimagesizefromstring(base64_decode($m[1]));

        return $info ? [(float) $info[0], (float) $info[1]] : null;
    }
}
