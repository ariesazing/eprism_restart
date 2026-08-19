<?php
    // Geometry (header/footer height, gap, print-safe padding, side margins) is resolved
    // once in SubmissionPdfComposer::resolveGeometry() and shared with SubmissionPdfMerger,
    // so the composed content pages here and the header/footer stamped onto attachment
    // pages stay in sync instead of drifting apart.
    [
        'headerHeight' => $headerHeight, 'footerHeight' => $footerHeight, 'gap' => $gap, 'printSafePad' => $printSafePad,
        'imagePadding' => $imagePadding, 'marginLeft' => $marginLeft, 'marginRight' => $marginRight,
    ] = $geometry;

    $headerReserve = $headerHeight + $gap + $printSafePad;
    $footerReserve = $footerHeight + $gap + $printSafePad;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: {{ $headerReserve }}px {{ $marginRight }}px {{ $footerReserve }}px {{ $marginLeft }}px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        /*
         * top/bottom stay relative to the content box (unchanged by printSafePad, which only
         * grows the @page margin above) — see SubmissionPdfComposer::resolveGeometry doc
         * comment for why. That's what pushes the header/footer content down/up from the
         * physical page edge without admins needing to account for it in the height they pick.
         */
        header {
            position: fixed; top: -{{ $headerHeight + $gap }}px; left: 0px; right: 0px; height: {{ $headerHeight }}px;
            box-sizing: border-box; overflow: hidden; text-align: center; padding: 4px 0;
        }
        header p { margin: 0; font-size: 10px; color: #334155; }
        header strong { font-size: 12px; color: #b91c1c; letter-spacing: 0.5px; }
        footer {
            position: fixed; bottom: -{{ $footerHeight + $gap }}px; left: 0px; right: 0px; height: {{ $footerHeight }}px;
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
