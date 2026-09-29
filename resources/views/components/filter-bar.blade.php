@props(['action', 'hasActiveFilters' => false, 'clearUrl' => null])
<div {{ $attributes->merge(['class' => 'research-filter']) }}>
    <form method="GET" action="{{ $action }}" data-table-search class="flex flex-wrap items-end gap-3">
        {{ $slot }}
        <button type="submit" title="Search" aria-label="Search" class="rounded-xl bg-slate-800 p-2.5 text-white"><svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10" cy="10" r="6" stroke-width="2"/><path d="m15 15 5 5" stroke-width="2"/></svg></button>
        @if ($hasActiveFilters && $clearUrl)
            <a href="{{ $clearUrl }}" title="Clear filters" aria-label="Clear filters" class="rounded-xl border border-slate-300 px-3 py-2 text-slate-700">&times;</a>
        @endif
    </form>
</div>
