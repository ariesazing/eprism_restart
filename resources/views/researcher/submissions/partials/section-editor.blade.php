@php
    $sectionsByKey = $sections->keyBy('section_key');
    $disabled = $disabled ?? false;
@endphp

@unless ($disabled)
    <div class="flex flex-wrap gap-2" data-wizard-controls>
        @foreach ($template->sections as $index => $definition)
            <button type="button" data-wizard-chapter="{{ $index }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 transition hover:border-red-300 hover:text-red-700">
                {{ $index + 1 }}. {{ $definition->label }}
            </button>
        @endforeach
    </div>
@endunless

<div class="mt-4 grid gap-4" data-chapters>
    @foreach ($template->sections as $definition)
        @php $section = $sectionsByKey->get($definition->key); @endphp
        <div id="section-{{ $definition->key }}" data-chapter-panel class="rounded-2xl border border-slate-200 p-5">
            <h4 class="text-sm font-semibold text-slate-900">{{ $definition->label }}</h4>

            @if ($definition->type === 'table')
                <div class="mt-4 overflow-x-auto" data-table-section>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-slate-300 text-left text-xs font-medium text-slate-500">
                                <th class="px-2 py-2">#</th>
                                @foreach ($definition->columns as $column)
                                    <th class="px-2 py-2">{{ $column['label'] }}</th>
                                @endforeach
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        @php $rows = $section?->tableRows() ?? []; @endphp
                        <tbody data-table-rows data-next-index="{{ count($rows) }}">
                            @forelse ($rows as $index => $row)
                                @include('researcher.submissions.partials.table-row', [
                                    'index' => $index,
                                    'columns' => $definition->columns,
                                    'row' => $row,
                                    'sectionKey' => $definition->key,
                                    'disabled' => $disabled,
                                ])
                            @empty
                                @include('researcher.submissions.partials.table-row', [
                                    'index' => 0,
                                    'columns' => $definition->columns,
                                    'row' => [],
                                    'sectionKey' => $definition->key,
                                    'disabled' => $disabled,
                                ])
                            @endforelse
                        </tbody>
                    </table>

                    @unless ($disabled)
                        <button type="button" data-add-row class="mt-3 text-xs font-medium text-red-700">+ Add row</button>
                        <template data-row-template>
                            @include('researcher.submissions.partials.table-row', [
                                'index' => '__INDEX__',
                                'columns' => $definition->columns,
                                'row' => [],
                                'sectionKey' => $definition->key,
                                'disabled' => false,
                            ])
                        </template>
                    @endunless
                </div>
            @else
                <div class="mt-4">
                    @if ($disabled)
                        <div class="prose prose-sm max-w-none rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                            {!! $section?->content ?: '<p class="italic text-slate-400">No content provided.</p>' !!}
                        </div>
                    @else
                        <input type="hidden" id="section-{{ $definition->key }}-input" name="sections[{{ $definition->key }}]" value="" />
                        <div data-richtext-editor data-hidden-input="section-{{ $definition->key }}-input">{!! $section?->content !!}</div>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
