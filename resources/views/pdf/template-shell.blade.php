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
    @include('pdf.partials.zone-content-style')
    <style>
        @page { margin: {{ $headerReserve }}px {{ $marginRight }}px {{ $footerReserve }}px {{ $marginLeft }}px; }
        /*
         * position:fixed content is placed relative to the body's own content box, whose top
         * edge sits $headerReserve down from the physical page edge (the @page margin above).
         * Offsetting by -($headerReserve - $headerTop) pulls the header up from there so its
         * own top edge lands exactly $headerTop from the physical page edge — matching
         * canvas-editor's header.top semantics (see resolveGeometry's doc comment) instead of
         * an independent number, so the editor and this PDF agree on where it sits. Height
         * itself is $headerHeight/$footerHeight — grown by PdfContentHeightMeasurer beyond the
         * admin's configured minimum when the actual content needs more room, so this "just"
         * positions and clips at whatever that resolved to; it's not deciding the size itself.
         */
        header { position: fixed; top: -{{ $headerReserve - $headerTop }}px; left: 0px; right: 0px; height: {{ $headerHeight }}px; overflow: hidden; }
        footer { position: fixed; bottom: -{{ $footerReserve - $footerBottom }}px; left: 0px; right: 0px; height: {{ $footerHeight }}px; overflow: hidden; }
        /*
         * dompdf's overflow:hidden support is unreliable for position:fixed content, so an
         * uploaded letterhead image taller than the reserved header/footer box (its own
         * width/height HTML attributes, e.g. from an <img> pasted into the template editor)
         * would still bleed into the body instead of being clipped. Constraining the image
         * itself — not just its container — is what actually keeps it inside the box: this
         * only ever bites when content still exceeds PdfContentHeightMeasurer's safety cap
         * (MAX_ZONE_HEIGHT) and genuinely has to clip — the normal case already grew the box
         * to fit, so this cap is just a floor under how small an oversized image can get.
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
