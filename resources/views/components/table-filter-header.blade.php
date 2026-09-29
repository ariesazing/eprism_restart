@props(['label', 'filterName' => null, 'name' => null, 'options' => [], 'currentValue' => null, 'current' => null, 'sortParam' => null, 'currentSort' => null, 'sortDirection' => null, 'sortable' => false, 'align' => 'left', 'sortAsc' => 'asc', 'sortDesc' => 'desc'])
@php
    $filterName = $filterName ?? $name;
    $currentValue = $currentValue ?? $current;
    $sortParam = $sortParam ?? ($sortable ? 'sort' : null);
    $currentSort = $currentSort ?? $sortDirection;
    $nextSort = $currentSort === 'asc' ? $sortDesc : $sortAsc;
    $popoverId = 'filter-'.\Illuminate\Support\Str::uuid();
@endphp
<th scope="col" data-column-label="{{ $label }}" @if($sortParam) aria-sort="{{ $currentSort === 'asc' ? 'ascending' : 'descending' }}" @endif {{ $attributes->merge(['class' => 'px-4 py-3 font-semibold text-slate-700 text-xs uppercase tracking-wider text-'.$align]) }}>
    @if ($sortParam)
        <a href="{{ request()->fullUrlWithQuery([$sortParam => $nextSort, 'page' => null]) }}" class="inline-flex items-center gap-1" title="Sort by {{ $label }}">
            {{ $label }}<svg aria-hidden="true" class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M8 4v16m-4-4 4 4 4-4M16 20V4m-4 4 4-4 4 4"/></svg>
        </a>
    @else
        {{ $label }}
    @endif
    @if ($filterName && count($options))
        <span x-data="{ expanded: false }">
            <button type="button" popovertarget="{{ $popoverId }}" class="table-header-control" @if($currentValue !== null && $currentValue !== '') data-active="true" @endif aria-label="Filter {{ $label }}" title="Filter {{ $label }}" :aria-expanded="expanded.toString()">
                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="{{ $popoverId }}" popover="auto" class="table-filter-popover" @beforetoggle="expanded = $event.newState === 'open'; if (expanded) { const r = $el.previousElementSibling.getBoundingClientRect(); $el.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 280)) + 'px'; $el.style.top = Math.max(8, Math.min(r.bottom + 6, window.innerHeight - 280)) + 'px'; }">
                <strong>Filter {{ $label }}</strong>
                <a href="{{ request()->fullUrlWithQuery([$filterName => null, 'page' => null]) }}" class="mt-2 block rounded-lg p-2 hover:bg-blue-50">All {{ $label }}</a>
                @foreach ($options as $value => $option)
                    <a href="{{ request()->fullUrlWithQuery([$filterName => $value, 'page' => null]) }}" @if((string) $currentValue === (string) $value) aria-current="true" @endif class="block rounded-lg p-2 hover:bg-blue-50 {{ (string) $currentValue === (string) $value ? 'bg-blue-50 font-bold text-blue-700' : '' }}">{{ $option }}</a>
                @endforeach
            </div>
        </span>
    @endif
</th>
