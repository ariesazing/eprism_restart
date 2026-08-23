{{--
    Single-series trend over time: a 2px line, a ~10% opacity area wash under it (never a
    saturated fill), recessive hairline gridlines with clean-number ticks, and a direct
    label only on the endpoint — the one point the story is about. One series needs no
    legend box; the card title already says what's plotted. Each point still carries a
    native tooltip so its exact value is reachable on hover/focus, not just at the
    highlighted endpoint.
--}}
@props(['data', 'color' => '#a9233a'])

@php
    $points = collect($data)->values();
    $count = $points->count();
    $maxValue = max(1, (int) $points->max('count'));

    // Four even, whole-number steps (not four independently-rounded quarters of the max,
    // which produces uneven-looking ticks like 0/1/3/4/5) — the smallest "nice" step that
    // still reaches the data.
    $niceSteps = [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000];
    $step = collect($niceSteps)->first(fn ($s) => $s * 4 >= $maxValue) ?? (int) ceil($maxValue / 4);
    $axisMax = $step * 4;

    $width = 720;
    $height = 220;
    $paddingLeft = 30;
    $paddingRight = 12;
    $paddingTop = 16;
    $paddingBottom = 26;
    $plotWidth = $width - $paddingLeft - $paddingRight;
    $plotHeight = $height - $paddingTop - $paddingBottom;
    $stepX = $count > 1 ? $plotWidth / ($count - 1) : 0;

    $coords = $points->map(function ($point, $i) use ($paddingLeft, $paddingTop, $plotHeight, $axisMax, $stepX) {
        return [
            'x' => round($paddingLeft + $i * $stepX, 1),
            'y' => round($paddingTop + $plotHeight - ($point->count / $axisMax) * $plotHeight, 1),
        ];
    })->values();

    $linePath = $coords->map(fn ($c, $i) => ($i === 0 ? 'M' : 'L').$c['x'].' '.$c['y'])->implode(' ');
    $baseline = $paddingTop + $plotHeight;
    $areaPath = $count > 0
        ? $linePath." L{$coords->last()['x']} {$baseline} L{$coords->first()['x']} {$baseline} Z"
        : '';

    $gridLines = collect([0, 0.25, 0.5, 0.75, 1])->map(fn ($f) => [
        'y' => round($paddingTop + $plotHeight * (1 - $f), 1),
        'value' => (int) round($axisMax * $f),
    ]);

    $showEveryOther = $count > 8;
@endphp

<div class="w-full">
    @if ($count > 0)
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" style="max-height: 260px;" role="img" aria-label="Submission volume over time">
            @foreach ($gridLines as $line)
                <line x1="{{ $paddingLeft }}" y1="{{ $line['y'] }}" x2="{{ $width - $paddingRight }}" y2="{{ $line['y'] }}" stroke="#e1e0d9" stroke-width="1" />
                <text x="{{ $paddingLeft - 6 }}" y="{{ $line['y'] + 3 }}" text-anchor="end" font-size="10" fill="#898781">{{ $line['value'] }}</text>
            @endforeach

            <path d="{{ $areaPath }}" fill="{{ $color }}" fill-opacity="0.1" stroke="none" />
            <path d="{{ $linePath }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

            @foreach ($coords as $i => $c)
                <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="{{ $i === $count - 1 ? 4 : 3 }}" fill="{{ $color }}" stroke="#fff" stroke-width="2">
                    <title>{{ $points[$i]->label }}: {{ $points[$i]->count }}</title>
                </circle>
            @endforeach

            @if ($count > 0)
                <text x="{{ $coords->last()['x'] }}" y="{{ $coords->last()['y'] - 10 }}" text-anchor="end" font-size="11" font-weight="600" fill="#0b0b0b">{{ $points->last()->count }}</text>
            @endif

            @foreach ($coords as $i => $c)
                @continue($showEveryOther && $i % 2 === 1 && $i !== $count - 1)
                <text x="{{ $c['x'] }}" y="{{ $height - 8 }}" text-anchor="middle" font-size="9" fill="#898781">{{ $points[$i]->label }}</text>
            @endforeach
        </svg>
    @else
        <div class="flex h-40 items-center justify-center rounded-xl border border-dashed border-slate-300 text-sm text-slate-400">No submissions yet.</div>
    @endif
</div>
