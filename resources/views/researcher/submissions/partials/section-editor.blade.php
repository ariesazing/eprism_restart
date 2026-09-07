@php
    $sectionsByKey = $sections->keyBy('section_key');
    $disabled = $disabled ?? false;
    $missingSectionKeys = $missingSectionKeys ?? [];
@endphp

@if ($disabled)
    {{-- Read-only: no wizard tabs at all (initChapterWizard requires
         [data-wizard-controls] to do anything — without it every panel just stays
         visible, which is exactly right for a locked submission), so every chapter
         renders in one plain, single-column stack. --}}
    <div class="mt-4 grid min-w-0 gap-4" data-chapters>
        @include('researcher.submissions.partials.chapter-panels')
    </div>
@else
    {{--
        Chapter tabs sit to the left of the panel they switch between (Google Docs-style
        document tabs), not stacked above it — see initChapterWizard in
        submission-editor.js, which already only shows one [data-chapter-panel] at a time
        and highlights the matching [data-wizard-chapter] button; it doesn't care where
        either one sits in the layout, so this is a pure CSS/structure change.
    --}}
    <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
        <div class="lg:w-56 lg:shrink-0">
            <div class="flex flex-wrap gap-2 lg:sticky lg:top-4 lg:flex-col lg:flex-nowrap" data-wizard-controls>
                @foreach ($template->sections as $index => $definition)
                    <button type="button" data-wizard-chapter="{{ $index }}" data-section-key="{{ $definition->key }}" class="relative rounded-xl border border-slate-300 px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:border-cherry-300 hover:text-cherry-700 lg:w-full">
                        {{ $index + 1 }}. {{ $definition->label }}
                        <span data-missing-dot class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-rose-600 ring-2 ring-white" title="This section still needs content" @if (! in_array($definition->key, $missingSectionKeys, true)) hidden @endif></span>
                    </button>
                @endforeach
            </div>
            <span data-autosave-status class="mt-3 block text-xs font-medium text-slate-400"></span>
        </div>

        <div class="min-w-0 flex-1 grid gap-4" data-chapters>
            @include('researcher.submissions.partials.chapter-panels')
        </div>
    </div>
@endif
