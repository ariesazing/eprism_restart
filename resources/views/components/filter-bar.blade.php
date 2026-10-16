@props(['action', 'hasActiveFilters' => false, 'clearUrl' => null, 'formClass' => null, 'submitLabel' => null, 'clearLabel' => null])
<div {{ $attributes->merge(['class' => 'research-filter']) }}>
    <form method="GET" action="{{ $action }}" data-table-search class="{{ $formClass ?? 'flex flex-wrap items-end gap-3' }}">
        {{ $slot }}
        <button type="submit" title="{{ $submitLabel ?? 'Search' }}" aria-label="{{ $submitLabel ?? 'Search' }}" class="{{ $submitLabel ? 'research-filter-apply inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-95' : 'rounded-xl bg-slate-800 p-2.5 text-white' }}">
            <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10" cy="10" r="6" stroke-width="2"/><path d="m15 15 5 5" stroke-width="2"/></svg>
            @if ($submitLabel)<span>{{ $submitLabel }}</span>@endif
        </button>
        @if ($hasActiveFilters && $clearUrl)
            <a href="{{ $clearUrl }}" title="Clear filters" aria-label="Clear filters" class="{{ $clearLabel ? 'inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100' : 'rounded-xl border border-slate-300 px-3 py-2 text-slate-700' }}">@if ($clearLabel){{ $clearLabel }}@else&#215;@endif</a>
        @endif
    </form>
</div>
