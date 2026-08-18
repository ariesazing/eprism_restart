<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $templateKey }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $templateLabel }}</h2>
            </div>
            <a href="{{ route('admin.document-templates.index') }}" class="text-sm font-medium text-red-700">Back to templates</a>
        </div>
    </x-slot>

    @vite(['resources/js/submission-editor.js'])

    <div class="py-10">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:px-8 lg:grid-cols-[1fr,320px]">
            @if ($errors->any())
                <div class="lg:col-span-2 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.document-templates.update', $templateKey) }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf
                <p class="mb-3 text-xs text-slate-500">Header and footer (top and bottom of the page) are part of this same document &mdash; click into them directly to edit.</p>

                <input type="hidden" id="template-content" name="content" value="" />
                <input type="hidden" id="template-page-options" name="page_options" value="" />
                <input type="hidden" id="template-body-html" name="body_html" value="" />
                <input type="hidden" id="template-header-html" name="header_html" value="" />
                <input type="hidden" id="template-footer-html" name="footer_html" value="" />

                <div
                    data-canvas-editor="toolbar"
                    data-content-input="template-content"
                    data-page-options-input="template-page-options"
                    data-body-input="template-body-html"
                    data-header-input="template-header-html"
                    data-footer-input="template-footer-html"
                    data-image-upload-url="{{ route('admin.document-templates.images.store') }}"
                >
                    <div data-canvas-toolbar class="document-toolbar"></div>
                    <div data-canvas-mount style="height: 700px;" class="mt-3 overflow-y-auto overflow-x-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"></div>
                </div>
                <script type="application/json" data-canvas-editor-data>{!! json_encode(array_merge((array) ($editorData ?: []), ['pageOptions' => $pageOptions])) !!}</script>

                <div class="mt-4 flex items-center gap-3 border-t border-slate-100 pt-4">
                    <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Save Template</button>

                    @if ($hasPreviewSubmission)
                        <button
                            type="submit"
                            formaction="{{ route('admin.document-templates.preview', $templateKey) }}"
                            formtarget="_blank"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Preview
                        </button>
                    @else
                        <span class="text-xs text-slate-400">Preview unavailable &mdash; no {{ $templateLabel }} submission exists yet.</span>
                    @endif
                </div>
            </form>

            <aside class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-sm font-semibold text-slate-900">Available Placeholders</h3>
                <p class="mt-1 text-xs text-slate-500">Keep these tokens intact when editing &mdash; they get replaced with real submission data when the document is generated. They work anywhere in the document, including the header and footer.</p>

                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Fields</p>
                    <ul class="mt-1 space-y-1 font-mono text-xs text-slate-600">
                        @foreach ($placeholders['scalars'] as $scalar)
                            @php($token = '$'.'{'.$scalar.'}')
                            <li>{{ $token }}</li>
                        @endforeach
                    </ul>
                </div>

                @foreach ($placeholders['each'] as $block)
                    @php($openTag = '{'.'{#each '.$block['key'].'}'.'}')
                    @php($closeTag = '{'.'{/each}'.'}')
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Repeating: {{ $block['key'] }}</p>
                        <p class="mt-1 font-mono text-xs text-slate-500">{{ $openTag }} ... {{ $closeTag }}</p>
                        <ul class="mt-1 space-y-1 font-mono text-xs text-slate-600">
                            @foreach ($block['fields'] as $field)
                                @php($fieldToken = '$'.'{'.$field.'}')
                                <li>{{ $fieldToken }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </aside>
        </div>
    </div>
</x-app-layout>
