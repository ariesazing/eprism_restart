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

<div>
    @if ($total > 0)
        <div class="flex h-7 gap-0.5 overflow-hidden rounded-lg">
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

    <div class="mt-3 grid gap-1.5 sm:grid-cols-2">
        @foreach ($items as $segment)
            @php $pct = $total > 0 ? round(($segment['value'] / $total) * 100) : 0; @endphp
            <div class="flex items-center justify-between gap-2 text-xs">
                <span class="flex items-center gap-1.5 text-slate-600">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $segment['color'] }};"></span>
                    {{ $segment['label'] }}
                </span>
                <span class="font-semibold tabular-nums text-slate-900">{{ $segment['value'] }} <span class="font-normal text-slate-400">({{ $pct }}%)</span></span>
            </div>
        @endforeach
    </div>
</div>
