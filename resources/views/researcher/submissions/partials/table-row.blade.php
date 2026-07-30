@php
    $disabled = $disabled ?? false;
@endphp
<tr data-table-row class="border-b border-slate-200">
    <td class="px-2 py-2 text-xs text-slate-400" data-row-number>{{ is_numeric($index) ? $index + 1 : '' }}</td>
    @foreach ($columns as $column)
        <td class="px-2 py-2">
            <input
                type="text"
                name="sections[{{ $sectionKey }}][{{ $index }}][{{ $column['key'] }}]"
                value="{{ $row[$column['key']] ?? '' }}"
                class="w-full rounded-lg border-slate-300 text-sm"
                @disabled($disabled)
            />
        </td>
    @endforeach
    <td class="px-2 py-2 text-right">
        @unless($disabled)
            <button type="button" data-remove-row class="text-xs font-medium text-rose-600">Remove</button>
        @endunless
    </td>
</tr>
