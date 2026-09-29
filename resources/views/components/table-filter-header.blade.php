@props([
    'label',
    'filterName' => null,
    'name' => null,
    'options' => [],
    'currentValue' => null,
    'current' => null,
    'sortParam' => null,
    'currentSort' => null,
    'sortDirection' => null,
    'sortable' => false,
    'align' => 'left',
])

@php
    $effectiveFilterName = $filterName ?? $name;
    $effectiveCurrentValue = $currentValue ?? $current;
    $effectiveSortParam = $sortParam ?? ($sortable ? 'sort' : null);
    $effectiveCurrentSort = $currentSort ?? $sortDirection;

    $hasFilter = !empty($effectiveFilterName) && !empty($options);
    $isActive = $hasFilter && $effectiveCurrentValue !== null && $effectiveCurrentValue !== '';
    $hasSort = !empty($effectiveSortParam);
    $nextSort = $effectiveCurrentSort === 'asc' ? 'desc' : 'asc';
@endphp

<th scope="col" {{ $attributes->merge(['class' => 'px-4 py-3 font-semibold text-slate-700 text-xs uppercase tracking-wider ' . ($align === 'right' ? 'text-right' : 'text-left')]) }}>
    <div class="inline-flex items-center gap-1.5 {{ $align === 'right' ? 'justify-end' : 'justify-start' }}" x-data="{ open: false }">
        @if ($hasSort)
            <a href="{{ request()->fullUrlWithQuery([$sortParam => $nextSort]) }}" class="group inline-flex items-center gap-1 hover:text-blue-600 transition" title="Sort by {{ $label }}">
                <span>{{ $label }}</span>
                <span class="inline-flex items-center text-blue-600 group-hover:text-blue-700">
                    @if ($currentSort === 'asc')
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                    @elseif ($currentSort === 'desc')
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    @else
                        <svg class="h-3.5 w-3.5 text-blue-400 opacity-60 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    @endif
                </span>
            </a>
        @else
            <span>{{ $label }}</span>
        @endif

        @if ($hasFilter)
            <div class="relative inline-block text-left" @click.outside="open = false">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center justify-center h-6 w-6 rounded hover:bg-slate-200/80 transition {{ $isActive ? 'text-blue-600 font-bold bg-blue-50 ring-1 ring-blue-300' : 'text-slate-400 hover:text-slate-700' }}"
                    title="Filter by {{ $label }}"
                    aria-label="Filter by {{ $label }}"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    @if ($isActive)
                        <span class="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full bg-blue-600"></span>
                    @endif
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute z-30 mt-2 min-w-[12rem] max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl text-xs font-normal normal-case {{ $align === 'right' ? 'right-0' : 'left-0' }}"
                >
                    <div class="px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-100 mb-1">
                        Filter by {{ $label }}
                    </div>
                    <a
                        href="{{ request()->fullUrlWithQuery([$filterName => null, 'page' => null]) }}"
                        class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-slate-700 hover:bg-slate-50 transition {{ !$isActive ? 'bg-blue-50/70 font-semibold text-blue-700' : '' }}"
                    >
                        <span>All {{ $label }}</span>
                        @if (!$isActive)
                            <svg class="h-3.5 w-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    </a>
                    @foreach ($options as $key => $opt)
                        @php
                            $val = is_array($opt) ? ($opt['value'] ?? $key) : (is_object($opt) ? ($opt->value ?? $opt->id) : $key);
                            $optLabel = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? $val) : (is_object($opt) ? ($opt->name ?? (method_exists($opt, 'label') ? $opt->label() : (string)$opt)) : $opt);
                            $isSelected = (string)$currentValue === (string)$val;
                        @endphp
                        <a
                            href="{{ request()->fullUrlWithQuery([$filterName => $val, 'page' => null]) }}"
                            class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-slate-700 hover:bg-slate-50 transition {{ $isSelected ? 'bg-blue-50/70 font-semibold text-blue-700' : '' }}"
                        >
                            <span class="truncate">{{ $optLabel }}</span>
                            @if ($isSelected)
                                <svg class="h-3.5 w-3.5 text-blue-600 shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</th>
