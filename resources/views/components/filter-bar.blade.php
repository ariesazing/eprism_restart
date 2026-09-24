{{--
    One consistent filter panel for every list page in the app: fields flow in a single
    row (wrapping on narrow viewports) and the whole panel collapses behind a toggle so it
    can be collapsed when space is needed. Starts expanded so filters are easy to find.
--}}
@props(['action', 'hasActiveFilters' => false, 'clearUrl' => null])

<div {{ $attributes->merge(['class' => 'research-filter app-card bg-white']) }} x-data="{ open: true }">
    <button type="button" :aria-expanded="open.toString()" @click="open = ! open" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left">
        <span class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <svg class="h-4 w-4 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16M7 12h10M10 19h4"></path></svg>
            Filters
            @if ($hasActiveFilters)
                <span class="rounded-full bg-cherry-100 px-2 py-0.5 text-xs font-semibold text-cherry-700">Active</span>
            @endif
        </span>
        <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="filter-motion-enter"
         x-transition:enter-start="filter-motion-hidden"
         x-transition:enter-end="filter-motion-visible"
         x-transition:leave="filter-motion-leave"
         x-transition:leave-start="filter-motion-visible"
         x-transition:leave-end="filter-motion-hidden"
         class="research-filter-panel border-t border-slate-100 p-4">
        <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
            {{ $slot }}
            <div class="flex gap-2">
                <button type="submit" class="research-filter-apply rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Apply</button>
                @if ($hasActiveFilters && $clearUrl)
                    <a href="{{ $clearUrl }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>
