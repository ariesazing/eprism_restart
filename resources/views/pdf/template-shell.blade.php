<?php
    // Geometry (reserved header/footer band, offset, side margins) is resolved once in
    // SubmissionPdfComposer::resolveGeometry() and shared with SubmissionPdfMerger, so the
    // composed content pages here and the header/footer stamped onto attachment pages stay
    // in sync instead of drifting apart.
    [
        'headerReserve' => $headerReserve, 'footerReserve' => $footerReserve,
        'headerTop' => $headerTop, 'footerBottom' => $footerBottom,
        'headerHeight' => $headerHeight, 'footerHeight' => $footerHeight,
        'imagePadding' => $imagePadding, 'marginLeft' => $marginLeft, 'marginRight' => $marginRight,
    ] = $geometry;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: {{ $headerReserve }}px {{ $marginRight }}px {{ $footerReserve }}px {{ $marginLeft }}px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        /*
         * position:fixed content is placed relative to the body's own content box, whose top
         * edge sits $headerReserve down from the physical page edge (the @page margin above).
         * Offsetting by -($headerReserve - $headerTop) pulls the header up from there so its
         * own top edge lands exactly $headerTop from the physical page edge — matching
         * canvas-editor's header.top semantics (see resolveGeometry's doc comment) instead of
         * an independent number, so the editor and this PDF agree on where it sits.
         */
        header {
            position: fixed; top: -{{ $headerReserve - $headerTop }}px; left: 0px; right: 0px; height: {{ $headerHeight }}px;
            box-sizing: border-box; overflow: hidden; text-align: center; padding: 4px 0;
        }
        header p { margin: 0; font-size: 10px; color: #334155; }
        header strong { font-size: 12px; color: #b91c1c; letter-spacing: 0.5px; }
        footer {
            position: fixed; bottom: -{{ $footerReserve - $footerBottom }}px; left: 0px; right: 0px; height: {{ $footerHeight }}px;
            box-sizing: border-box; overflow: hidden; text-align: center; font-size: 8px; color: #94a3b8; padding: 4px 0;
        }
        footer p { margin: 0; }
        p { margin: 0 0 8px 0; }
        strong { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table td, table th { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9px; text-align: left; vertical-align: top; }
        ul, ol { margin: 0 0 8px 0; padding-left: 20px; }
        img { max-width: 100%; }
        /*
         * dompdf's overflow:hidden support is unreliable for position:fixed content, so an
         * uploaded letterhead image taller than the reserved header/footer box (its own
         * width/height HTML attributes, e.g. from an <img> pasted into the template editor)
         * would still bleed into the body instead of being clipped. Constraining the image
         * itself — not just its container — is what actually keeps it inside the box:
         * max-height caps it to the available space and width:auto preserves its aspect
         * ratio, so it shrinks to fit rather than overflowing.
         */
        header img { max-height: {{ max(0, $headerHeight - $imagePadding) }}px; width: auto; max-width: 100%; }
        footer img { max-height: {{ max(0, $footerHeight - $imagePadding) }}px; width: auto; max-width: 100%; }
    </style>
</head>
<body>
    <header>
        {!! $headerHtml !!}
    </header>
    <footer>
        {!! $footerHtml !!}
    </footer>

    {!! $bodyHtml !!}
</body>
</html>
