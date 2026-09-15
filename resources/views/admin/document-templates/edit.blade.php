{{--
    x-focus-layout (no sidebar) — editing a template's docx and its manuscript formatting policy
    is a dedicated task, not a page within the app's usual section-to-section browsing (same
    reasoning as submissions/document-review.blade.php and reviewer/submissions/show.blade.php).
    The header slot below carries its own "Back to templates" link, so nothing reachable via the
    sidebar is lost — and dropping it leaves the full viewport width for the 3-column layout
    below (manuscript formatting / ONLYOFFICE editor / available placeholders), each of the two
    side columns collapsible so the editor itself can take up as much width as needed.
--}}
<x-focus-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $templateKey }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $templateLabel }}</h2>
            </div>
            <a href="{{ route('admin.document-templates.index') }}" class="text-sm font-medium text-cherry-700">Back to templates</a>
        </div>
    </x-slot>

    @vite(['resources/js/submission-editor.js'])

    {{-- No JS-measured height here on purpose (a fragile `getBoundingClientRect()` +
         `window.scrollY` mix previously caused the whole page to grow scrollable whenever a
         sidebar was expanded, since a stale/incorrectly-computed max-height silently falls
         back to "no limit" rather than clamping). `100dvh` is a plain, always-valid CSS value
         — same pattern as submissions/partials/pdf-viewer.blade.php's own chapter rail. --}}
    <div
        class="mx-auto flex max-w-[1800px] flex-col items-start gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:px-8"
        x-data="{ leftOpen: {{ $manuscriptFormatOptions !== null ? 'true' : 'false' }}, rightOpen: true }"
    >
        {{-- Left: manuscript formatting — collapses to a slim rail so the editor can reclaim
             the width when it's not needed. --}}
        @if ($manuscriptFormatOptions !== null)
            <aside
                class="w-full shrink-0 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition-[width,padding] lg:sticky lg:top-4 lg:max-h-[calc(100dvh-11rem)] lg:overflow-y-auto lg:overscroll-contain"
                :class="leftOpen ? 'p-4 lg:w-80' : 'p-2 lg:w-12'"
            >
                <button type="button" @click="leftOpen = ! leftOpen" aria-label="Toggle manuscript formatting" :aria-expanded="leftOpen" class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100 hover:text-cherry-700" :class="leftOpen ? 'justify-between' : 'justify-center'">
                    <span x-show="leftOpen">Manuscript Formatting</span>
                    <svg class="h-4 w-4 shrink-0 rotate-90 transition-transform lg:rotate-0" :class="{ 'lg:rotate-180': ! leftOpen }" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                </button>
                <div x-show="leftOpen" class="mt-3">
                    @include('admin.document-templates.partials.manuscript-format')
                </div>
            </aside>
        @endif

        {{-- Center: the ONLYOFFICE editor itself, filling whatever width/height remains. --}}
        <div class="flex w-full min-w-0 flex-1 flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="mb-2 text-xs text-slate-500">
                Header/footer: use Word's own Insert &gt; Header/Footer. Formatting is entirely up to
                how you format the document here. Changes save automatically as you edit.
            </p>

            {{--
                No <form>/save button here on purpose: ONLYOFFICE persists through its own
                save/force-save callback loop (see OnlyOfficeTemplateController), the same
                as a researcher's chapter — there's nothing left to submit.
            --}}
            <div
                data-onlyoffice-editor
                data-config-url="{{ route('admin.document-templates.onlyoffice-config', $templateKey) }}"
                data-office-url="{{ config('services.onlyoffice.url') }}"
                class="min-h-0 flex-1"
            >
                <p data-onlyoffice-message role="status" class="mb-2 text-xs text-slate-500">Loading the editor…</p>
                <div data-onlyoffice-mount id="onlyoffice-template-mount" class="h-[60vh] overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"></div>
            </div>
        </div>

        {{-- Right: available placeholders — collapsible for the same reason as the left rail. --}}
        <aside
            class="w-full shrink-0 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition-[width,padding] lg:sticky lg:top-4 lg:max-h-[calc(100dvh-11rem)] lg:overflow-y-auto lg:overscroll-contain"
            :class="rightOpen ? 'p-5 lg:w-80' : 'p-2 lg:w-12'"
        >
            <button type="button" @click="rightOpen = ! rightOpen" aria-label="Toggle available placeholders" :aria-expanded="rightOpen" class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-sm font-semibold text-slate-900 hover:bg-slate-100 hover:text-cherry-700" :class="rightOpen ? 'justify-between' : 'justify-center'">
                <span x-show="rightOpen">Available Placeholders</span>
                <svg class="h-4 w-4 shrink-0 rotate-90 transition-transform lg:rotate-0" :class="{ 'lg:rotate-180': ! rightOpen }" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7" /></svg>
            </button>

            <div x-show="rightOpen" class="mt-2">
                <p class="text-xs text-slate-500">Type these tokens directly into the document, exactly as shown.</p>

                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Fields</p>
                    <ul class="mt-1 space-y-1 font-mono text-xs text-slate-600">
                        @foreach ($placeholders['scalars'] as $scalar)
                            @include('admin.document-templates.partials.copyable-token', ['token' => '$'.'{'.$scalar.'}'])
                        @endforeach
                    </ul>
                </div>

                @if ($placeholders['chapters'] ?? [])
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Chapters (rich text)</p>
                        <p class="mt-1 text-xs text-slate-500">Each of these is written separately by the researcher in ONLYOFFICE and inserted at its own token below, replacing the token itself &mdash; write its heading directly above the token (e.g. a bold "Chapter 1" line), the same way you'd write any other heading in this document. Each token must be the <strong>only</strong> thing on its own paragraph &mdash; not inline with other text, and not inside a table cell.</p>
                        <ul class="mt-2 space-y-1 font-mono text-xs">
                            @foreach ($placeholders['chapters'] as $chapter)
                                @include('admin.document-templates.partials.copyable-token', ['token' => '$'.'{'.$chapter['key'].'}'])
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($manuscriptSections ?? [])
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Manuscript sections</p>
                        <p class="mt-1 text-xs text-slate-500">Place each section token on its own paragraph, in manuscript order. It becomes an editable chapter with its required heading. Sections without a token are appended. Use these for tables researchers will enter in the document, too. Metadata remains read-only.</p>
                        <ul class="mt-2 space-y-1 font-mono text-xs">
                            @foreach ($manuscriptSections as $section)
                                @include('admin.document-templates.partials.copyable-token', ['token' => '$'.'{section:'.$section->key.'}'])
                            @endforeach
                        </ul>
                    </div>
                @endif

                @foreach ($placeholders['each'] as $block)
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Repeating: {{ $block['key'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Either build one table row with these tokens as its cells, or &mdash; if you don't want it tabled &mdash;
                            put <code>{{ '${'.$block['key'].'}' }}</code> on its own paragraph, the repeating content after it, then <code>{{ '${/'.$block['key'].'}' }}</code> on its own paragraph.
                            Either way it repeats automatically, once per item.
                        </p>
                        <ul class="mt-1 space-y-1 font-mono text-xs text-slate-600">
                            @foreach ($block['fields'] as $field)
                                @include('admin.document-templates.partials.copyable-token', ['token' => '$'.'{'.$field.'}'])
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</x-focus-layout>
