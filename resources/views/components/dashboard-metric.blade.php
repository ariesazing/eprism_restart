@props(['label', 'value', 'tone' => 'total', 'total' => null, 'caption' => null])
@php $percentage = $total > 0 ? min(100, round($value / $total * 100)) : 0; @endphp
<div class="metric-card reference-metric" data-tone="{{ $tone }}">
    <span class="reference-metric-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            @if ($tone === 'approved')<path d="m5 12 4 4L19 6"/>
            @elseif ($tone === 'review')<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 3"/>
            @elseif ($tone === 'attention')<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8m0-8-8 8"/>
            @else<path d="M6 3h8l4 4v14H6Z M14 3v5h4 M9 12h6 M9 16h6"/>@endif
        </svg>
    </span>
    <p class="reference-metric-label">{{ $label }}</p>
    <p class="reference-metric-value">{{ $value }}</p>
    <p class="reference-metric-caption">{{ $caption ?? ($percentage.'% of total') }}</p>
    @if ($total !== null)
        <div class="reference-metric-track" aria-hidden="true"><span style="width: {{ $percentage }}%"></span></div>
    @endif
</div>
