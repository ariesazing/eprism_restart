@props(['segments'])

@php
    $items = collect($segments);
    $maximum = max(1, (int) $items->max('value'));
    $step = max(1, (int) ceil($maximum / 4));
    $axisMax = $step * 4;
@endphp

<div class="report-columns-scroll">
    <div class="report-columns">
        <div class="report-columns-plot" aria-hidden="true">
            @foreach (range(0, 4) as $tick)
                <div class="report-column-grid" style="bottom: {{ $tick * 25 }}%"><span>{{ $step * $tick }}</span></div>
            @endforeach
            @foreach ($items as $item)
                <div class="report-column-slot">
                    <div class="report-column" style="height: {{ $item['value'] / $axisMax * 100 }}%; background: {{ $item['color'] }}">
                        <strong>{{ number_format($item['value']) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="report-column-labels">
            @foreach ($items as $item)
                <div>{{ $item['label'] }}<span class="sr-only">: {{ $item['value'] }} submissions</span></div>
            @endforeach
        </div>
    </div>
</div>
<p class="report-chart-note">Number of submissions by revision count</p>
