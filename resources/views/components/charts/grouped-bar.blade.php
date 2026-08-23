{{--
    Grouped column chart — series ARE the subject here (Basic vs Action research), so this
    is genuine categorical identity color, not magnitude. Columns rounded at the top (the
    data end), square at the baseline, capped at 24px thick per the standard mark spec.
    A legend always ships for 2+ series (color-matching alone is never the only channel).
--}}
@props(['categories', 'series', 'data'])

@php
    $chartHeight = 140;
    $maxValue = max(1, (int) collect($data)->max());
@endphp

<div>
    <div class="flex items-end justify-around gap-6 border-b border-slate-200" style="height: {{ $chartHeight }}px;">
        @foreach ($categories as $catKey => $catLabel)
            <div class="flex h-full items-end gap-3">
                @foreach ($series as $seriesKey => $seriesInfo)
                    @php
                        $value = (int) ($data["$seriesKey:$catKey"] ?? 0);
                        $barHeight = max(2, round(($value / $maxValue) * $chartHeight));
                    @endphp
                    <div class="flex w-6 flex-col items-center justify-end" title="{{ $seriesInfo['label'] }} &middot; {{ $catLabel }}: {{ $value }}">
                        <span class="mb-1 text-xs font-semibold tabular-nums text-slate-700">{{ $value }}</span>
                        <div class="w-full rounded-t-md" style="height: {{ $barHeight }}px; background-color: {{ $seriesInfo['color'] }};"></div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="mt-2 flex justify-around text-center text-xs font-medium text-slate-500">
        @foreach ($categories as $catLabel)
            <div>{{ $catLabel }}</div>
        @endforeach
    </div>
    <div class="mt-4 flex flex-wrap gap-4">
        @foreach ($series as $seriesInfo)
            <div class="flex items-center gap-2 text-xs text-slate-600">
                <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $seriesInfo['color'] }};"></span>
                {{ $seriesInfo['label'] }}
            </div>
        @endforeach
    </div>
</div>
