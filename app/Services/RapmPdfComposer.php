<?php

namespace App\Services;

use App\Models\SubmissionDocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Mirrors SubmissionPdfComposer's compose/overlay split, but for RAPM's review-summary and
 * routing-slip documents: same pdf.template-shell + PdfGeometryResolver plumbing, just fed
 * prebuilt scalars/each instead of a ResearchSubmission's own chapters. There's no attachment
 * merging step (SubmissionPdfMerger) — these documents have no uploaded files to append.
 */
class RapmPdfComposer
{
    public function __construct(
        private readonly RapmTemplateRenderer $renderer,
        private readonly PdfGeometryResolver $geometryResolver,
    ) {}

    /**
     * @param  array<string, array{value: string, raw: bool}>  $scalars
     * @param  array<string, array<int, array<string, string>>>  $each
     */
    public function compose(SubmissionDocumentTemplate $documentTemplate, array $scalars, array $each): string
    {
        $overlay = $this->composeHeaderFooterOverlay($documentTemplate, $scalars, $each);

        $html = view('pdf.template-shell', [
            'bodyHtml' => $this->renderer->render($documentTemplate->body_html ?? '', $scalars, $each),
            'headerHtml' => $overlay['headerHtml'],
            'footerHtml' => $overlay['footerHtml'],
            'geometry' => $overlay['geometry'],
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /**
     * @param  array<string, array{value: string, raw: bool}>  $scalars
     * @param  array<string, array<int, array<string, string>>>  $each
     * @return array{headerHtml: string, footerHtml: string, geometry: array<string, int>}
     */
    public function composeHeaderFooterOverlay(SubmissionDocumentTemplate $documentTemplate, array $scalars, array $each): array
    {
        $headerHtml = $this->renderer->render($documentTemplate->header_html ?? '', $scalars, $each);
        $footerHtml = $this->renderer->render($documentTemplate->footer_html ?? '', $scalars, $each);

        return [
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
            'geometry' => $this->geometryResolver->resolve($documentTemplate->page_options, $headerHtml, $footerHtml),
        ];
    }
}
