<x-app-layout skeleton="form">
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

    <div class="py-10">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:px-8 lg:grid-cols-[1fr,320px]">
            <div class="min-w-0 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <p class="mb-3 text-xs text-slate-500">
                    Header and footer are part of this same document &mdash; use Word's own
                    Insert &gt; Header/Footer to edit them directly. Formatting (fonts, sizes,
                    spacing, alignment) is entirely up to how you format the document here;
                    there's no separate auto-format configuration anymore. Changes save
                    automatically as you edit, the same way a researcher's chapter does.
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
                >
                    <p data-onlyoffice-message role="status" class="mb-2 text-xs text-slate-500">Loading the editor…</p>
                    <div data-onlyoffice-mount id="onlyoffice-template-mount" class="mt-2 h-[80vh] min-h-[600px] overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"></div>
                </div>
            </div>

            <aside class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-sm font-semibold text-slate-900">Available Placeholders</h3>
                <p class="mt-1 text-xs text-slate-500">Type these tokens directly into the document, exactly as shown.</p>

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
            </aside>
        </div>
    </div>
</x-app-layout>
