{{--
    Part-to-whole as a single stacked bar rather than a donut/pie — donuts read angles,
    which people are worse at comparing than the lengths a stacked bar gives them for free.
    Segments keep a 2px surface gap (never a border) to stay visually distinct, and every
    segment is paired with a legend row (label + value + share) so identity never rides on
    color alone.
--}}
@props(['segments'])

@php
    $items = collect($segments);
    $total = (int) $items->sum('value');
@endphp

<div class="report-distribution">
    <div class="report-distribution-total"><strong>{{ number_format($total) }}</strong><span>Total records in breakdown</span></div>
    @if ($total > 0)
        <div class="report-segments" aria-hidden="true">
            @foreach ($items as $segment)
                @php $pct = ($segment['value'] / $total) * 100; @endphp
                @if ($pct > 0)
                    <div
                        style="width: {{ $pct }}%; background-color: {{ $segment['color'] }};"
                        title="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ round($pct) }}%)"
                    ></div>
                @endif
            @endforeach
        </div>
    @else
        <div class="flex h-7 items-center justify-center rounded-lg border border-dashed border-slate-300 text-xs text-slate-400">No data yet</div>
    @endif

    <div class="report-legend">
        @foreach ($items as $segment)
            @php $pct = $total > 0 ? round(($segment['value'] / $total) * 100) : 0; @endphp
            <div class="report-legend-row">
                <span class="flex min-w-0 items-center gap-2 text-slate-600">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $segment['color'] }};"></span>
                    {{ $segment['label'] }}
                </span>
                <strong class="tabular-nums text-slate-900">{{ number_format($segment['value']) }}</strong>
                <span class="report-share">{{ $pct }}%</span>
            </div>
        @endforeach
    </div>
</div>
