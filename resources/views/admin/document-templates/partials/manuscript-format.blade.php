{{--
    Manuscript formatting: the ONLYOFFICE per-chapter engine's own docx-level formatting policy
    (see the `manuscript_format_options` column and scripts/manuscript.py's apply_formatting()) —
    a completely separate field and pipeline from the legacy auto-format options above, which
    belong only to the retired HTML/dompdf 'canvas_editor' engine. Applied to a temporary
    assembled copy of a submission's manuscript during generation, after chapters are spliced in
    and before ONLYOFFICE converts it to PDF; never touches the researcher's or admin's own
    source documents.

    Expects $manuscriptFormatOptions (array, already normalized) and $manuscriptFonts (list of
    strings) from DocumentTemplateController::edit().
--}}
@php
    $mf = $manuscriptFormatOptions ?? [];
    $body = $mf['body'] ?? [];
    $heading1 = $mf['heading1'] ?? [];
    $heading23 = $mf['heading23'] ?? [];
    $table = $mf['table'] ?? [];
    $caption = $mf['caption'] ?? [];
    $page = $mf['page'] ?? [];
    $alignments = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'];
@endphp

<div class="min-w-0" x-data="{ enabled: {{ ($mf['enabled'] ?? false) ? 'true' : 'false' }} }">
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs text-slate-500">
            Controls how a generated submission's manuscript is formatted &mdash; body text, headings,
            tables, captions and page margins. Anything left blank keeps whatever formatting is
            already in this document.
        </p>
    </div>
    <label class="mt-3 flex items-center gap-2 text-xs font-medium text-slate-700">
        <input type="hidden" name="manuscript_format[enabled]" value="0" form="manuscript-format-form">
        <input type="checkbox" name="manuscript_format[enabled]" value="1" form="manuscript-format-form"
            x-model="enabled"
            class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600"
            @checked($mf['enabled'] ?? false)>
        Apply this policy
    </label>

    <form id="manuscript-format-form" method="POST" action="{{ route('admin.document-templates.manuscript-format.update', $templateKey) }}" class="mt-4 space-y-5" :class="{ 'opacity-50': !enabled }">
        @csrf

        {{-- Body text --}}
        <fieldset class="rounded-xl border border-slate-200 p-3">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Body text</legend>
            <p class="mt-1 text-xs text-slate-500">Applies to ordinary paragraphs within each researcher-written chapter. List items get everything except first-line indent, since their indent already comes from the list itself.</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="text-xs text-slate-600">Font
                    <select name="manuscript_format[body][font]" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Unchanged</option>
                        @foreach ($manuscriptFonts as $font)
                            <option value="{{ $font }}" @selected(($body['font'] ?? null) === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-slate-600">Size (pt)
                    <input type="number" min="6" max="72" step="0.5" name="manuscript_format[body][size]" value="{{ $body['size'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">Alignment
                    <select name="manuscript_format[body][alignment]" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Unchanged</option>
                        @foreach ($alignments as $value => $option)
                            <option value="{{ $value }}" @selected(($body['alignment'] ?? null) === $value)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-slate-600">Line spacing
                    <input type="number" min="0.5" max="4" step="0.05" name="manuscript_format[body][line_spacing]" value="{{ $body['line_spacing'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">Space before (pt)
                    <input type="number" min="0" max="200" step="1" name="manuscript_format[body][space_before]" value="{{ $body['space_before'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">Space after (pt)
                    <input type="number" min="0" max="200" step="1" name="manuscript_format[body][space_after]" value="{{ $body['space_after'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">First-line indent (in)
                    <input type="number" min="0" max="3" step="0.05" name="manuscript_format[body][first_line_indent]" value="{{ $body['first_line_indent'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
            </div>
        </fieldset>

        {{-- Headings --}}
        @foreach ([['heading1', 'Headings &mdash; H1', $heading1], ['heading23', 'Headings &mdash; H2/H3', $heading23]] as [$prefix, $legend, $values])
            <fieldset class="rounded-xl border border-slate-200 p-3">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{!! $legend !!}</legend>
                <p class="mt-1 text-xs text-slate-500">Recognized by the paragraph's real heading level in the document &mdash; never by how it looks (bold, size, capitalization).</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <label class="text-xs text-slate-600">Font
                        <select name="manuscript_format[{{ $prefix }}][font]" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Unchanged</option>
                            @foreach ($manuscriptFonts as $font)
                                <option value="{{ $font }}" @selected(($values['font'] ?? null) === $font)>{{ $font }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs text-slate-600">Size (pt)
                        <input type="number" min="6" max="72" step="0.5" name="manuscript_format[{{ $prefix }}][size]" value="{{ $values['size'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <label class="text-xs text-slate-600">Alignment
                        <select name="manuscript_format[{{ $prefix }}][alignment]" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option value="">Unchanged</option>
                            @foreach ($alignments as $value => $option)
                                <option value="{{ $value }}" @selected(($values['alignment'] ?? null) === $value)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs text-slate-600">Space before (pt)
                        <input type="number" min="0" max="200" step="1" name="manuscript_format[{{ $prefix }}][space_before]" value="{{ $values['space_before'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <label class="text-xs text-slate-600">Space after (pt)
                        <input type="number" min="0" max="200" step="1" name="manuscript_format[{{ $prefix }}][space_after]" value="{{ $values['space_after'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <div class="col-span-2 flex flex-wrap items-center gap-x-4 gap-y-2 pb-1">
                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                            <input type="checkbox" name="manuscript_format[{{ $prefix }}][bold]" value="1" class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600" @checked($values['bold'] ?? false)>
                            Bold
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                            <input type="checkbox" name="manuscript_format[{{ $prefix }}][keep_with_next]" value="1" class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600" @checked($values['keep_with_next'] ?? false)>
                            Keep with next
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                            <input type="checkbox" name="manuscript_format[{{ $prefix }}][page_break_before]" value="1" class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600" @checked($values['page_break_before'] ?? false)>
                            Page break before
                        </label>
                    </div>
                </div>
            </fieldset>
        @endforeach

        {{-- Tables --}}
        <fieldset class="rounded-xl border border-slate-200 p-3" x-data="{ inherit: {{ ($table['inherit'] ?? true) ? 'true' : 'false' }} }">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Tables</legend>
            <p class="mt-1 text-xs text-slate-500">Only the text inside table cells &mdash; row/column layout, merged cells and borders are always left exactly as authored.</p>
            <label class="mt-2 flex items-center gap-1.5 text-xs text-slate-600">
                <input type="hidden" name="manuscript_format[table][inherit]" value="0">
                <input type="checkbox" name="manuscript_format[table][inherit]" value="1" x-model="inherit" class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600" @checked($table['inherit'] ?? true)>
                Inherit this template's own table formatting (leave table text as authored)
            </label>
            <div class="mt-3 grid grid-cols-2 gap-3" :class="{ 'opacity-50': inherit }">
                <label class="text-xs text-slate-600">Font
                    <select name="manuscript_format[table][font]" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="inherit">
                        <option value="">Unchanged</option>
                        @foreach ($manuscriptFonts as $font)
                            <option value="{{ $font }}" @selected(($table['font'] ?? null) === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-slate-600">Size (pt)
                    <input type="number" min="6" max="72" step="0.5" name="manuscript_format[table][size]" value="{{ $table['size'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="inherit">
                </label>
                <label class="text-xs text-slate-600">Alignment
                    <select name="manuscript_format[table][alignment]" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="inherit">
                        <option value="">Unchanged</option>
                        @foreach ($alignments as $value => $option)
                            <option value="{{ $value }}" @selected(($table['alignment'] ?? null) === $value)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </fieldset>

        {{-- Captions --}}
        <fieldset class="rounded-xl border border-slate-200 p-3" x-data="{ inherit: {{ ($caption['inherit'] ?? true) ? 'true' : 'false' }} }">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Captions</legend>
            <p class="mt-1 text-xs text-slate-500">Recognized only by a real "Caption" style (e.g. Word's Insert&nbsp;Caption) &mdash; never by text starting with "Figure" or "Table".</p>
            <label class="mt-2 flex items-center gap-1.5 text-xs text-slate-600">
                <input type="hidden" name="manuscript_format[caption][inherit]" value="0">
                <input type="checkbox" name="manuscript_format[caption][inherit]" value="1" x-model="inherit" class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600" @checked($caption['inherit'] ?? true)>
                Inherit this template's own caption formatting
            </label>
            <div class="mt-3 grid grid-cols-2 gap-3" :class="{ 'opacity-50': inherit }">
                <label class="text-xs text-slate-600">Font
                    <select name="manuscript_format[caption][font]" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="inherit">
                        <option value="">Unchanged</option>
                        @foreach ($manuscriptFonts as $font)
                            <option value="{{ $font }}" @selected(($caption['font'] ?? null) === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-slate-600">Size (pt)
                    <input type="number" min="6" max="72" step="0.5" name="manuscript_format[caption][size]" value="{{ $caption['size'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="inherit">
                </label>
                <label class="text-xs text-slate-600">Alignment
                    <select name="manuscript_format[caption][alignment]" class="mt-1 w-full rounded-md border-slate-300 text-sm" :disabled="inherit">
                        <option value="">Unchanged</option>
                        @foreach ($alignments as $value => $option)
                            <option value="{{ $value }}" @selected(($caption['alignment'] ?? null) === $value)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-end gap-1.5 pb-1 text-xs text-slate-600">
                    <input type="checkbox" name="manuscript_format[caption][italic]" value="1" class="h-4 w-4 rounded border-slate-300 text-cherry-700 focus:ring-cherry-600" :disabled="inherit" @checked($caption['italic'] ?? false)>
                    Italic
                </label>
            </div>
        </fieldset>

        {{-- Page layout --}}
        <fieldset class="rounded-xl border border-slate-200 p-3">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Page layout</legend>
            <p class="mt-1 text-xs text-slate-500">Margins only &mdash; applied to every section (including a landscape chapter), which keeps its own orientation and page breaks untouched.</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="text-xs text-slate-600">Top (in)
                    <input type="number" min="0.25" max="3" step="0.05" name="manuscript_format[page][margin_top]" value="{{ $page['margin_top'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">Right (in)
                    <input type="number" min="0.25" max="3" step="0.05" name="manuscript_format[page][margin_right]" value="{{ $page['margin_right'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">Bottom (in)
                    <input type="number" min="0.25" max="3" step="0.05" name="manuscript_format[page][margin_bottom]" value="{{ $page['margin_bottom'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs text-slate-600">Left (in)
                    <input type="number" min="0.25" max="3" step="0.05" name="manuscript_format[page][margin_left]" value="{{ $page['margin_left'] ?? '' }}" placeholder="Unchanged" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </label>
            </div>
        </fieldset>

        <div class="flex flex-col gap-2">
            <button type="submit" class="w-full rounded-lg bg-cherry-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cherry-800">Save manuscript formatting</button>
            @if ($hasManuscriptDocx)
                <button type="submit" formaction="{{ route('admin.document-templates.manuscript-format.preview', $templateKey) }}" formtarget="_blank" class="w-full rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Preview formatting</button>
            @else
                <span class="text-xs text-slate-400">Open the editor above and save at least once before previewing.</span>
            @endif
        </div>
    </form>
</div>
