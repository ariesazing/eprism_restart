{{--
    Single-hue magnitude bars (e.g. "research by organizational unit"). Every bar shares
    the same color deliberately — these are categories of one measure, not identity-bearing
    series, so per the categorical/sequential split a single hue is correct: bar length
    alone carries the comparison. Rounded only at the growing (data) end, square at the
    baseline, per the standard bar mark spec.
--}}
@props(['data', 'color' => '#a9233a'])

@php
    $items = collect($data)->values();
    $maxValue = max(1, (int) $items->max(fn ($item) => $item->value ?? $item['value']));
@endphp

<div class="grid gap-2.5">
    @forelse ($items as $item)
        @php
            $label = $item->label ?? $item['label'];
            $value = $item->value ?? $item['value'];
            $pct = $maxValue > 0 ? max(2, round(($value / $maxValue) * 100, 1)) : 2;
        @endphp
        <div class="flex items-center gap-3" title="{{ $label }}: {{ $value }}">
            <div class="w-40 shrink-0 truncate text-xs font-medium text-slate-600">{{ $label }}</div>
            <div class="h-3.5 flex-1 rounded-md bg-slate-100">
                <div class="h-full rounded-r-md" style="width: {{ $pct }}%; background-color: {{ $color }};"></div>
            </div>
            <div class="w-8 shrink-0 text-right text-xs font-semibold tabular-nums text-slate-900">{{ $value }}</div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No data yet.</div>
    @endforelse
</div>
