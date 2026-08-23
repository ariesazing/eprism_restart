<?php

namespace App\Services;

/**
 * Single source of truth for header/footer sizing (px) — consumed by template-shell.blade.php
 * for the composed content pages and by SubmissionPdfMerger for attachment pages, so both
 * surfaces stay visually consistent instead of drifting apart. Shared by both
 * SubmissionPdfComposer (research-submission chapters) and RapmPdfComposer (RAPM's
 * review-summary/routing-slip documents) since neither this math nor its inputs are
 * submission-specific.
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
 *   - `headerGap`/`footerGap` is the blank breathing room left between the header/
 *     footer's own content box and the body — this one has no canvas-editor equivalent
 *     at all (it's purely a dompdf rendering concern), so it's admin-set via Page
 *     Setup's own dedicated fields rather than mirrored from the editor.
 *
 * The height/reserve this resolves to is a *minimum*, not a fixed number: if
 * $headerHtml/$footerHtml actually needs more room than the admin's configured margin
 * leaves (measured via PdfContentHeightMeasurer), the reserved band grows to fit instead
 * of dompdf's overflow:hidden silently clipping it — the same auto-extend behavior
 * canvas-editor's own header/footer zone already has (HeaderParticle::getExtraHeight).
 */
class PdfGeometryResolver
{
    /**
     * Floor (px) for the header/footer reserved band, so a page whose top/bottom margin was
     * set very small (or missing) still leaves the ~4-5mm most printers can't print flush to
     * the paper edge without the header/footer content risking getting cut off.
     */
    private const MIN_RESERVE = 20;

    /**
     * Ceiling (px) PdfContentHeightMeasurer is allowed to grow a header/footer to. Without
     * this, one admin's unusually tall header/footer content — or a broken template — could
     * consume most of the page; this keeps growth generous but bounded. ~35% of an A4 page.
     */
    private const MAX_ZONE_HEIGHT = 400;

    /** A4 width (px, 96dpi) — matches PAGE_SIZE_PRESETS.A4 in resources/js/document-editor/toolbar.js. */
    private const PAGE_WIDTH_PX = 794;

    /**
     * Default breathing room (px) between the header/footer content's own box and where
     * body content starts/ends, used until an admin sets their own via Page Setup's
     * "Space between header and body" / "Space between body and footer" fields (page_options
     * headerGap/footerGap) — kept as a fallback so a template saved before those fields
     * existed renders exactly as it always did.
     */
    private const DEFAULT_GAP = 8;

    /** Breathing room (px) reserved inside the header/footer box, subtracted before capping image height. */
    private const IMAGE_PADDING = 8;

    public function __construct(
        private readonly PdfContentHeightMeasurer $measurer,
    ) {}

    /**
     * @return array{headerReserve: int, footerReserve: int, headerTop: int, footerBottom: int, headerHeight: int, footerHeight: int, imagePadding: int, marginLeft: int, marginRight: int}
     */
    public function resolve(?string $pageOptionsJson, string $headerHtml = '', string $footerHtml = ''): array
    {
        $pageOptions = $pageOptionsJson ? (json_decode($pageOptionsJson, true) ?: []) : [];

        $configuredHeaderReserve = max(self::MIN_RESERVE, (int) ($pageOptions['margins'][0] ?? 100));
        $configuredFooterReserve = max(self::MIN_RESERVE, (int) ($pageOptions['margins'][2] ?? 70));

        $headerTop = max(0, min($configuredHeaderReserve, (int) ($pageOptions['header']['top'] ?? 30)));
        $footerBottom = max(0, min($configuredFooterReserve, (int) ($pageOptions['footer']['bottom'] ?? 30)));

        $headerGap = max(0, (int) ($pageOptions['headerGap'] ?? self::DEFAULT_GAP));
        $footerGap = max(0, (int) ($pageOptions['footerGap'] ?? self::DEFAULT_GAP));

        $configuredHeaderHeight = max(self::MIN_RESERVE, $configuredHeaderReserve - $headerTop - $headerGap);
        $configuredFooterHeight = max(self::MIN_RESERVE, $configuredFooterReserve - $footerBottom - $footerGap);

        $marginLeft = max(0, (int) ($pageOptions['margins'][3] ?? 60));
        $marginRight = max(0, (int) ($pageOptions['margins'][1] ?? 60));
        $contentWidthPx = max(1, self::PAGE_WIDTH_PX - $marginLeft - $marginRight);

        $headerHeight = max(
            $configuredHeaderHeight,
            $this->measurer->measure('header', $headerHtml, $contentWidthPx, $configuredHeaderHeight, self::MAX_ZONE_HEIGHT)
        );
        $footerHeight = max(
            $configuredFooterHeight,
            $this->measurer->measure('footer', $footerHtml, $contentWidthPx, $configuredFooterHeight, self::MAX_ZONE_HEIGHT)
        );

        return [
            'headerReserve' => max($configuredHeaderReserve, $headerTop + $headerHeight + $headerGap),
            'footerReserve' => max($configuredFooterReserve, $footerBottom + $footerHeight + $footerGap),
            'headerTop' => $headerTop,
            'footerBottom' => $footerBottom,
            'headerHeight' => $headerHeight,
            'footerHeight' => $footerHeight,
            'imagePadding' => self::IMAGE_PADDING,
            'marginLeft' => $marginLeft,
            'marginRight' => $marginRight,
        ];
    }
}
