@php
    $sectionsByKey = $sections->keyBy('section_key');
    $disabled = $disabled ?? false;
@endphp

@unless ($disabled)
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-2" data-wizard-controls>
            @foreach ($template->sections as $index => $definition)
                <button type="button" data-wizard-chapter="{{ $index }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 transition hover:border-cherry-300 hover:text-cherry-700">
                    {{ $index + 1 }}. {{ $definition->label }}
                </button>
            @endforeach
        </div>
        <span data-autosave-status class="text-xs font-medium text-slate-400"></span>
    </div>
@endunless

<div class="mt-4 grid min-w-0 gap-4" data-chapters>
    @foreach ($template->sections as $definition)
        @php $section = $sectionsByKey->get($definition->key); @endphp
        <div id="section-{{ $definition->key }}" data-chapter-panel class="min-w-0 rounded-2xl border border-slate-200 p-5">
            <h4 class="text-sm font-semibold text-slate-900">{{ $definition->label }}</h4>

            @if ($definition->type === 'table')
                <div class="mt-4 overflow-x-auto" data-table-section data-section-key="{{ $definition->key }}">
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
                        @php
                            $rows = $section?->tableRows() ?? [];
                            // The @empty branch below still renders one blank starter row
                            // when there are no saved rows, so the DOM always has at least
                            // one <tr> — next-index must count that rendered row, not the
                            // (possibly empty) saved-rows array, or "+ Add row" reuses index
                            // 0 and the new row's inputs collide with the starter row's.
                            $renderedRowCount = count($rows) ?: 1;
                        @endphp
                        <tbody data-table-rows data-next-index="{{ $renderedRowCount }}">
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
                        <button type="button" data-add-row class="mt-3 text-xs font-medium text-cherry-700">+ Add row</button>
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
                            {!! $section?->content_html ?: '<p class="italic text-slate-400">No content provided.</p>' !!}
                        </div>
                    @else
                        {{--
                            Seeded with the section's already-saved content, not blank: the
                            chapter wizard only mounts canvas-editor for a panel once its tab
                            is opened (see initChapterWizard in submission-editor.js), so a
                            chapter the researcher never clicks into this session must still
                            submit its existing content instead of overwriting it with "".
                        --}}
                        <input type="hidden" id="section-{{ $definition->key }}-content" name="sections[{{ $definition->key }}][content]" value="{{ $section?->content }}" />
                        <input type="hidden" id="section-{{ $definition->key }}-html" name="sections[{{ $definition->key }}][html]" value="{{ $section?->content_html }}" />
                        <div
                            class="min-w-0"
                            data-canvas-editor="toolbar-inline"
                            data-section-key="{{ $definition->key }}"
                            data-content-input="section-{{ $definition->key }}-content"
                            data-html-input="section-{{ $definition->key }}-html"
                        >
                            <div data-canvas-toolbar class="document-toolbar"></div>
                            <div data-canvas-mount style="height: 500px;" class="mt-2 overflow-y-auto overflow-x-hidden rounded-xl ring-1 ring-slate-200"></div>
                        </div>
                        <script type="application/json" data-canvas-editor-data>{!! json_encode(['main' => $section?->content ? json_decode($section->content, true)['main'] ?? [] : []]) !!}</script>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
