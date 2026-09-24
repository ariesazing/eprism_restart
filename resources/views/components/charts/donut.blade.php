@props(['segments', 'label' => 'Total'])

@php
    $items = collect($segments);
    $total = (int) $items->sum('value');
    $circumference = 2 * pi() * 70;
    $offset = 0;
@endphp

<div class="report-donut-layout">
    <div class="report-donut">
        <svg viewBox="0 0 180 180" aria-hidden="true">
            <circle cx="90" cy="90" r="70" fill="none" stroke="#f1f5f9" stroke-width="20" />
            @foreach ($items as $segment)
                @php
                    $length = $total > 0 ? ($segment['value'] / $total) * $circumference : 0;
                @endphp
                @if ($length > 0)
                    <circle cx="90" cy="90" r="70" fill="none" stroke="{{ $segment['color'] }}" stroke-width="20"
                        stroke-dasharray="{{ $length }} {{ $circumference }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 90 90)" />
                @endif
                @php($offset += $length)
            @endforeach
        </svg>
        <div class="report-donut-center"><strong>{{ number_format($total) }}</strong><span>{{ $total > 0 ? $label : 'No data yet' }}</span></div>
    </div>
    <div class="report-legend">
        @foreach ($items as $segment)
            <div class="report-legend-row">
                <span class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $segment['color'] }}" aria-hidden="true"></span>{{ $segment['label'] }}</span>
                <strong>{{ number_format($segment['value']) }}</strong>
                <span class="report-share">{{ $total > 0 ? round($segment['value'] / $total * 100) : 0 }}%</span>
            </div>
        @endforeach
    </div>
</div>
