<?php

namespace App\Services;

use Dompdf\Dompdf;

/**
 * Answers "how tall does this header/footer HTML actually want to render, at this width?"
 * — used by SubmissionPdfComposer::resolveGeometry() to grow the reserved header/footer
 * band to fit real content instead of always clipping to whatever the admin configured in
 * Page Setup. There's no direct way to ask dompdf this; the only stable, version-safe
 * signal it exposes is "did this content fit on one page at this page height" (page count
 * after render), so this binary-searches over candidate heights for the smallest one where
 * it still does — verified against dompdf's actual behavior (not assumed): the frame-tree
 * APIs that look like they'd give a height directly (Frame::get_margin_height() off
 * Dompdf::getTree()->get_root()) returned 0/null in practice, page-count-based search did not.
 */
class PdfContentHeightMeasurer
{
    /** CSS px -> pt, matching the 96dpi every other px value in this PDF pipeline assumes. */
    private const PX_TO_PT = 0.75;

    /** Binary search stops once the candidate range is this narrow (px) — plenty for a layout number. */
    private const TOLERANCE = 4;

    /**
     * @param  'header'|'footer'  $zone
     */
    public function measure(string $zone, string $html, int $widthPx, int $minPx, int $maxPx): int
    {
        if (trim($html) === '') {
            return $minPx;
        }

        $widthPt = $widthPx * self::PX_TO_PT;

        // Common case: whatever the admin already configured is enough — most headers/
        // footers are short (or, in every template seeded so far, a single letterhead
        // image), so this one extra render avoids the full search almost always.
        if ($this->fitsAt($zone, $html, $widthPt, $minPx)) {
            return $minPx;
        }

        // Even the safety cap isn't enough — an admin's content is unusually large. Clip
        // at the cap rather than let one broken template consume the whole page.
        if (! $this->fitsAt($zone, $html, $widthPt, $maxPx)) {
            return $maxPx;
        }

        $low = $minPx;
        $high = $maxPx;

        while ($high - $low > self::TOLERANCE) {
            $mid = intdiv($low + $high, 2);

            if ($this->fitsAt($zone, $html, $widthPt, $mid)) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return $high;
    }

    private function fitsAt(string $zone, string $html, float $widthPt, int $candidatePx): bool
    {
        $heightPt = max(1, $candidatePx) * self::PX_TO_PT;

        $pageHtml = view('pdf.measure-shell', ['zone' => $zone, 'html' => $html])->render();

        $dompdf = new Dompdf();
        $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
        $dompdf->loadHtml($pageHtml);
        $dompdf->render();

        return $dompdf->getCanvas()->get_page_count() <= 1;
    }
}
