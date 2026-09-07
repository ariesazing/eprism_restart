{{--
    Dedicated chapter/canvas-editor page — see ResearchSubmissionController::chapters().
    x-focus-layout (no sidebar): writing a chapter is a focused task in its own right, and
    the sidebar was competing for the same horizontal space the canvas and its left-side
    wizard tabs need. Content saves continuously via the same per-chapter autosave the main
    submission page always used (see data-autosave-url below) — there's no separate submit
    here, just a link back once you're done for this session.
--}}
<x-focus-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Chapters &middot; {{ $template->label }}</p>
            </div>
            <a href="{{ route('submissions.show', $submission) }}" class="text-sm font-medium text-cherry-700">&larr; Back to submission</a>
        </div>
    </x-slot>

    @vite(['resources/js/submission-editor.js'])

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @unless ($editable)
                <div class="mb-6 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200">
                    This submission is read-only while it is {{ strtolower($submission->status->label()) }}.
                </div>
            @endunless

            @if ($editable && ! $readiness['ready'])
                <div class="mb-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                    <p class="font-medium">This submission isn't ready to send for review yet:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($readiness['sections']['missing'] as $missing)
                            <li><button type="button" data-jump-to-section="{{ $missing['key'] }}" class="font-medium underline hover:no-underline">{{ $missing['label'] }}</button> still needs content.</li>
                        @endforeach
                        @foreach ($readiness['attachments']['missing'] as $label)
                            <li>{{ $label }} still needs to be uploaded (from the main submission page).</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form data-section-editor-form @if ($editable) data-autosave-url="{{ route('submissions.autosave', $submission) }}" @endif>
                @include('researcher.submissions.partials.section-editor', [
                    'template' => $template,
                    'sections' => $sections,
                    'disabled' => ! $editable,
                    'missingSectionKeys' => collect($readiness['sections']['missing'])->pluck('key')->all(),
                ])
            </form>
        </div>
    </div>
</x-focus-layout>
