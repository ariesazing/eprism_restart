<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class SubmissionPdfComposer
{
    /**
     * Floor (px) for the header/footer reserved band, so a page whose top/bottom margin was
     * set very small (or missing) still leaves the ~4-5mm most printers can't print flush to
     * the paper edge without the header/footer content risking getting cut off.
     */
    private const MIN_RESERVE = 20;

    /** Breathing room (px) between the header/footer content's own box and where body content starts/ends. */
    private const INNER_GAP = 8;

    /** Breathing room (px) reserved inside the header/footer box, subtracted before capping image height. */
    private const IMAGE_PADDING = 8;

    /**
     * CSS px -> pt, matching the 96dpi dompdf/template-shell.blade.php assumes for every
     * plain pixel value. SubmissionPdfMerger needs this too: TCPDF's own unitless HTML
     * image width/height are pt-equivalent (72dpi, verified empirically — an <img
     * height="100"> renders exactly 100pt tall), so a header/footer image stamped onto an
     * attachment page renders ~33% taller than the same px value produces in the dompdf
     * content pages unless it's first converted through this same ratio.
     */
    public const PX_TO_PT = 0.75;

    public function __construct(
        private readonly SubmissionHtmlTemplateRenderer $renderer,
    ) {}

    public function compose(ResearchSubmission $submission): string
    {
        $documentTemplate = $this->documentTemplate($submission);

        $html = view('pdf.template-shell', [
            'bodyHtml' => $this->renderer->render($documentTemplate->body_html ?? '', $submission),
            'headerHtml' => $this->renderer->render($documentTemplate->header_html ?? '', $submission),
            'footerHtml' => $this->renderer->render($documentTemplate->footer_html ?? '', $submission),
            'geometry' => $this->resolveGeometry($documentTemplate->page_options),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /**
     * Renders this submission's header/footer HTML plus the geometry used to size them, so
     * SubmissionPdfMerger can stamp the same header/footer onto uploaded attachment pages —
     * which, unlike the composed content pages above, never pass through template-shell and
     * would otherwise carry no header/footer at all.
     *
     * @return array{headerHtml: string, footerHtml: string, geometry: array<string, int>}
     */
    public function composeHeaderFooterOverlay(ResearchSubmission $submission): array
    {
        $documentTemplate = $this->documentTemplate($submission);

        return [
            'headerHtml' => $this->renderer->render($documentTemplate->header_html ?? '', $submission),
            'footerHtml' => $this->renderer->render($documentTemplate->footer_html ?? '', $submission),
            'geometry' => $this->resolveGeometry($documentTemplate->page_options),
        ];
    }

    private function documentTemplate(ResearchSubmission $submission): SubmissionDocumentTemplate
    {
        $templateKey = $submission->template()->key;
        $documentTemplate = SubmissionDocumentTemplate::active($templateKey);

        if ($documentTemplate === null) {
            throw new \RuntimeException("No document template configured for [{$templateKey}].");
        }

        return $documentTemplate;
    }

    /**
     * Single source of truth for header/footer sizing (px) — consumed by template-shell.blade.php
     * for the composed content pages and by SubmissionPdfMerger for attachment pages, so both
     * surfaces stay visually consistent instead of drifting apart.
     *
     * Mirrors how canvas-editor itself lays out the header/footer zone (verified against its
     * source: HeaderParticle.getHeaderTop/getExtraHeight, FooterParticle.getFooterBottom/
     * getExtraHeight in the shipped canvas-editor.js), rather than an independent scheme —
     * that's what keeps the WYSIWYG editor and the generated PDF from drifting apart:
     *   - `margins[0]`/`margins[2]` (the page's top/bottom margin, set in Page Setup) is the
     *     reserved band — where body content actually starts/ends, same as in the editor.
     *   - `header.top`/`footer.bottom` is an offset *within* that band — how far from the
     *     physical page edge the header/footer content itself starts — not a height. A
     *     *larger* offset leaves *less* room before hitting the margin, matching canvas-editor.
     *
     * @return array{headerReserve: int, footerReserve: int, headerTop: int, footerBottom: int, headerHeight: int, footerHeight: int, imagePadding: int, marginLeft: int, marginRight: int}
     */
    public function resolveGeometry(?string $pageOptionsJson): array
    {
        $pageOptions = $pageOptionsJson ? (json_decode($pageOptionsJson, true) ?: []) : [];

        $headerReserve = max(self::MIN_RESERVE, (int) ($pageOptions['margins'][0] ?? 100));
        $footerReserve = max(self::MIN_RESERVE, (int) ($pageOptions['margins'][2] ?? 70));

        $headerTop = max(0, min($headerReserve, (int) ($pageOptions['header']['top'] ?? 30)));
        $footerBottom = max(0, min($footerReserve, (int) ($pageOptions['footer']['bottom'] ?? 30)));

        return [
            'headerReserve' => $headerReserve,
            'footerReserve' => $footerReserve,
            'headerTop' => $headerTop,
            'footerBottom' => $footerBottom,
            'headerHeight' => max(self::MIN_RESERVE, $headerReserve - $headerTop - self::INNER_GAP),
            'footerHeight' => max(self::MIN_RESERVE, $footerReserve - $footerBottom - self::INNER_GAP),
            'imagePadding' => self::IMAGE_PADDING,
            'marginLeft' => max(0, (int) ($pageOptions['margins'][3] ?? 60)),
            'marginRight' => max(0, (int) ($pageOptions['margins'][1] ?? 60)),
        ];
    }
}
