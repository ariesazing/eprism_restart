<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class SubmissionPdfComposer
{
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
        private readonly PdfGeometryResolver $geometryResolver,
    ) {}

    /**
     * @param  array{headerHtml: string, footerHtml: string, geometry: array<string, int>}|null  $overlay
     *         Pass the result of a prior composeHeaderFooterOverlay() call for this same
     *         submission to reuse its (measurement-driven, non-trivial) geometry resolution
     *         instead of redoing it — SubmissionSnapshotService needs both this and the
     *         overlay for the same generation, and resolving geometry twice would render
     *         the header/footer measurement passes twice for no reason.
     */
    public function compose(ResearchSubmission $submission, ?array $overlay = null): string
    {
        $overlay ??= $this->composeHeaderFooterOverlay($submission);
        $documentTemplate = $this->documentTemplate($submission);

        $html = view('pdf.template-shell', [
            'bodyHtml' => $this->renderer->render($documentTemplate->body_html ?? '', $submission),
            'headerHtml' => $overlay['headerHtml'],
            'footerHtml' => $overlay['footerHtml'],
            'geometry' => $overlay['geometry'],
            'autoFormat' => $documentTemplate->auto_format_options ? json_decode($documentTemplate->auto_format_options, true) : [],
        ])->render();

        return $this->withHeadroomForLargeEmbeddedImages(fn () => Pdf::loadHTML($html)->setPaper('a4')->output());
    }

    /**
     * dompdf decodes every embedded image (base64 <img> src, most commonly a chapter's
     * pasted photo — resources/js/document-editor/index.js downscales those at paste time,
     * but this covers anything saved before that fix, or embedded some other way, e.g. a
     * proponent's uploaded photo) into memory in full for layout, on every single render.
     * A large-enough one can exceed the PHP memory_limit an ordinary request runs under,
     * which is an uncatchable fatal error — this only ever runs during submission's own
     * composePreview()/PDF work, not on every page load, so the extra headroom is scoped to
     * just that instead of raising the limit for the whole request.
     */
    private function withHeadroomForLargeEmbeddedImages(callable $callback): string
    {
        $previous = ini_get('memory_limit');

        ini_set('memory_limit', '1024M');

        try {
            return $callback();
        } finally {
            ini_set('memory_limit', $previous);
        }
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
        $headerHtml = $this->renderer->render($documentTemplate->header_html ?? '', $submission);
        $footerHtml = $this->renderer->render($documentTemplate->footer_html ?? '', $submission);

        return [
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
            'geometry' => $this->resolveGeometry($documentTemplate->page_options, $headerHtml, $footerHtml),
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
     * Thin wrapper kept so existing callers (e.g. DocumentTemplateController::preview())
     * don't need to know the geometry math moved to PdfGeometryResolver, which now also
     * backs RapmPdfComposer — see that class for the full explanation of this math.
     *
     * @return array{headerReserve: int, footerReserve: int, headerTop: int, footerBottom: int, headerHeight: int, footerHeight: int, imagePadding: int, marginLeft: int, marginRight: int}
     */
    public function resolveGeometry(?string $pageOptionsJson, string $headerHtml = '', string $footerHtml = ''): array
    {
        return $this->geometryResolver->resolve($pageOptionsJson, $headerHtml, $footerHtml);
    }
}
