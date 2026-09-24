{{--
    Grouped horizontal bars on a shared scale. Direct series labels and values keep
    comparisons readable without relying on color or hover. Zero counts have no fill.
--}}
@props(['categories', 'series', 'data'])

@php
    $maxValue = max(1, (int) collect($data)->max());
@endphp

<div class="report-comparison">
    <div class="grid gap-6">
        @foreach ($categories as $catKey => $catLabel)
            <div>
                <div class="mb-3 text-sm font-semibold text-slate-700">{{ $catLabel }}</div>
                <div class="grid gap-3">
                @foreach ($series as $seriesKey => $seriesInfo)
                    @php
                        $value = (int) ($data["$seriesKey:$catKey"] ?? 0);
                        $barWidth = round(($value / $maxValue) * 100, 2);
                    @endphp
                    <div class="report-comparison-row">
                        <span>{{ $seriesInfo['label'] }}</span>
                        <div class="report-track" aria-hidden="true"><div class="report-track-fill" style="width: {{ $barWidth }}%; background-color: {{ $seriesInfo['color'] }};"></div></div>
                        <strong>{{ number_format($value) }}</strong>
                    </div>
                @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <p class="report-chart-note">Submission counts · Shared scale across classifications</p>
</div>
