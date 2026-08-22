{{--
    Renders a header/footer's content in isolation, in normal document flow (no
    position:fixed, no height/overflow clipping) — used only by PdfContentHeightMeasurer
    to find out how tall $html actually wants to render at $widthPx wide, via a binary
    search over candidate page heights (see PdfContentHeightMeasurer::fitsAt). Shares
    zone-content-style so the measured height matches what template-shell.blade.php will
    actually produce instead of a second, independently-drifting notion of "how tall".
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.zone-content-style')
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; }
        {{-- position:static here (unlike the real header/footer) so content sits in normal
             flow and can push the page to a second one once it overflows $widthPx — that
             overflow is exactly the signal PdfContentHeightMeasurer::fitsAt reads. --}}
        header, footer { position: static; }
    </style>
</head>
<body>
    <{{ $zone }}>
        {!! $html !!}
    </{{ $zone }}>
</body>
</html>
