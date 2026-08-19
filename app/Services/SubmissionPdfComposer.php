<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class SubmissionPdfComposer
{
    /**
     * Breathing room (px) between the header/footer's own content and where body content
     * starts/ends. Verified against a real multi-table submission: a table's header row
     * landing as the last thing on a page came within ~10pt of the old, smaller gap —
     * dompdf's table pagination doesn't leave much slack when deciding a row still "fits",
     * so this needs enough margin to absorb that, not just font-metric rounding.
     */
    private const GAP = 28;

    /**
     * Extra clearance (px) between the physical page edge and the header/footer content
     * itself, on top of GAP. Most printers can't print flush to the paper edge (~4-5mm
     * unprintable margin), so a header/footer sitting at literal y=0 risks being cut off
     * when the generated document is actually printed.
     */
    private const PRINT_SAFE_PAD = 20;

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
     * @return array{headerHeight: int, footerHeight: int, gap: int, printSafePad: int, marginLeft: int, marginRight: int}
     */
    public function resolveGeometry(?string $pageOptionsJson): array
    {
        $pageOptions = $pageOptionsJson ? (json_decode($pageOptionsJson, true) ?: []) : [];

        return [
            'headerHeight' => max(20, (int) ($pageOptions['header']['top'] ?? 100)),
            'footerHeight' => max(20, (int) ($pageOptions['footer']['bottom'] ?? 70)),
            'gap' => self::GAP,
            'printSafePad' => self::PRINT_SAFE_PAD,
            'imagePadding' => self::IMAGE_PADDING,
            'marginLeft' => max(0, (int) ($pageOptions['margins'][3] ?? 60)),
            'marginRight' => max(0, (int) ($pageOptions['margins'][1] ?? 60)),
        ];
    }
}
