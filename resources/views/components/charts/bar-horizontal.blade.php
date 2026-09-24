{{--
    Single-hue magnitude bars (e.g. "research by organizational unit"). Every bar shares
    the same color deliberately — these are categories of one measure, not identity-bearing
    series, so per the categorical/sequential split a single hue is correct: bar length
    alone carries the comparison. Rounded only at the growing (data) end, square at the
    baseline, per the standard bar mark spec.
--}}
@props(['data', 'color' => '#d9123f'])

@php
    $items = collect($data)->values();
    $maxValue = max(1, (int) $items->max(fn ($item) => $item->value ?? $item['value']));
@endphp

<div class="report-ranking">
    @forelse ($items as $item)
        @php
            $label = $item->label ?? $item['label'];
            $value = $item->value ?? $item['value'];
            $pct = max(0, round(($value / $maxValue) * 100, 1));
        @endphp
        <div class="report-ranking-row">
            <span class="report-rank" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div class="min-w-0">
                <div class="report-ranking-label"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div>
                <div class="report-track" aria-hidden="true">
                    <div class="report-track-fill" style="width: {{ $pct }}%; background-color: {{ $color }};"></div>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No data yet.</div>
    @endforelse
</div>
