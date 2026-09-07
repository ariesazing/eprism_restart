{{--
    One chapter's panel per template section — shared between section-editor.blade.php's
    read-only (no wizard tabs) and editable (left-side wizard tabs) layouts, so the panel
    markup itself only has to exist once. Expects: $template, $sectionsByKey, $disabled,
    $missingSectionKeys (all already resolved by the including file).
--}}
@foreach ($template->sections as $definition)
    @php $section = $sectionsByKey->get($definition->key); $isMissing = in_array($definition->key, $missingSectionKeys, true); @endphp
    <div id="section-{{ $definition->key }}" data-chapter-panel data-section-key="{{ $definition->key }}" class="min-w-0 rounded-2xl border border-slate-200 p-5 @if ($isMissing) ring-2 ring-rose-300 @endif">
        <h4 class="text-sm font-semibold text-slate-900">{{ $definition->label }}</h4>
        <p data-missing-message class="mt-1 text-xs font-medium text-rose-600" @unless ($isMissing) hidden @endunless>This section is required and still needs content.</p>

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
                        {{--
                            No fixed height/overflow-y-auto here on purpose — the mount
                            grows to fit its rendered pages and the surrounding *page*
                            scrolls, instead of the editor carrying its own separate inner
                            scrollbar.
                        --}}
                        <div data-canvas-mount class="mt-2 overflow-x-hidden rounded-xl ring-1 ring-slate-200"></div>
                    </div>
                    <script type="application/json" data-canvas-editor-data>{!! json_encode(['main' => $section?->content ? json_decode($section->content, true)['main'] ?? [] : []]) !!}</script>
                @endif
            </div>
        @endif
    </div>
@endforeach
