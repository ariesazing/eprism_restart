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
            <div class="sticky top-2 z-10 flex flex-wrap gap-2 bg-slate-100/95 py-2 backdrop-blur lg:top-4 lg:flex-col lg:flex-nowrap lg:bg-transparent lg:py-0 lg:backdrop-blur-none" data-wizard-controls>
                {{-- Save now sits at the very top of the chapter list, inside the same sticky
                     group, so it's the first thing in the left panel and stays in view however far
                     the chapter is scrolled. Gold rather than cherry on purpose: the active chapter
                     tab below is already solid cherry, and this must not read as one more tab.
                     initChapterWizard() finds it by [data-manual-save-button] and the status line
                     by [data-autosave-status] — neither is tied to where it sits. --}}
                <div class="w-full lg:mb-1">
                    <button type="button" data-manual-save-button class="flex w-full items-center justify-center gap-2 rounded-xl bg-gold-400 px-4 py-3 text-sm font-bold text-gold-950 shadow-md ring-1 ring-gold-600/40 transition hover:bg-gold-300 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-cherry-700 active:scale-[.98]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" /><path d="M17 21v-8H7v8" /><path d="M7 3v5h8" /></svg>
                        Save now
                    </button>
                    <span data-autosave-status role="status" class="mt-1.5 block min-h-4 text-center text-xs font-medium text-slate-500"></span>

                    {{-- ONLYOFFICE can't underline grammar as you type (see ChapterGrammarReview), so this
                         opens a read-only review of the chapter with the issues highlighted. Canvas-editor
                         chapters keep their live squiggles and don't get it. --}}
                    @if ($submission->usesOnlyOffice() && ! $submission->usesManuscript())
                        <button type="button" data-grammar-review-button class="mt-1 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-cherry-300 hover:text-cherry-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-cherry-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h9M4 12h6M4 17h5" /><path d="m14 15 2.5 2.5L21 12" /></svg>
                            Check grammar
                        </button>
                    @endif
                </div>

                @foreach ($template->sections as $index => $definition)
                    <button type="button" data-wizard-chapter="{{ $index }}" data-section-key="{{ $definition->key }}" class="relative rounded-xl border border-slate-300 px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:border-cherry-300 hover:text-cherry-700 lg:w-full">
                        {{ $index + 1 }}. {{ $definition->label }}
                        <span data-missing-dot class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-rose-600 ring-2 ring-white" title="This section still needs content" @if (! in_array($definition->key, $missingSectionKeys, true)) hidden @endif></span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="min-w-0 flex-1 grid gap-4" data-chapters>
            @include('researcher.submissions.partials.chapter-panels')
        </div>
    </div>
@endif
