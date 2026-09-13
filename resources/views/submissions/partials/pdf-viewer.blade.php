@php
    $chapterSections = $submission->template()->sections;
@endphp

<div
    data-pdf-review
    data-document-url="{{ $documentViewUrl }}"
    data-comments-url="{{ $commentsUrl }}"
    data-channel="submission.{{ $submission->id }}"
    @if (! empty($snapshotId)) data-snapshot-id="{{ $snapshotId }}" @endif
    data-can-create="{{ $canCreate ? '1' : '0' }}"
    data-can-edit-all="{{ $canEditAll ? '1' : '0' }}"
    data-current-user-id="{{ auth()->id() }}"
    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6"
>
    {{-- Per-chapter jump tabs — same left-rail pattern as the researcher's chapter editor
         (see researcher/submissions/partials/section-editor.blade.php). Each button's
         target page is resolved client-side by searching the rendered PDF's extracted text
         for an invisible per-chapter marker the manuscript renderer embeds (see
         SubmissionHtmlTemplateRenderer::buildScalars()) — a manuscript generated before
         that renderer change has no marker to find, so pdf-review.js disables the button
         rather than jumping nowhere. --}}
    @if (! empty($chapterSections))
        <div class="lg:w-56 lg:shrink-0">
            <div class="sticky top-2 z-10 flex flex-wrap gap-2 bg-slate-100/95 py-2 backdrop-blur lg:top-4 lg:flex-col lg:flex-nowrap lg:bg-transparent lg:py-0 lg:backdrop-blur-none" data-chapter-nav>
                @foreach ($chapterSections as $index => $definition)
                    <button type="button" data-chapter-jump="{{ $definition->key }}" class="rounded-xl border border-slate-300 px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:border-cherry-300 hover:text-cherry-700 disabled:cursor-not-allowed disabled:opacity-40 lg:w-full">
                        {{ $index + 1 }}. {{ $definition->label }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="min-w-0 flex-1 overflow-x-auto">
        <div class="hidden mb-4 flex-wrap items-center justify-between gap-3" data-document-view-controls>
            <div class="flex gap-2">
                <button type="button" data-doc-view-mode="scroll" class="rounded-full px-4 py-2 text-xs font-medium">Scrollable</button>
                <button type="button" data-doc-view-mode="paginated" class="rounded-full px-4 py-2 text-xs font-medium">Paginated</button>
            </div>
            <div class="hidden items-center gap-3" data-doc-pagination-controls>
                <button type="button" data-doc-page-prev class="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">&larr; Previous</button>
                <span class="text-xs font-medium text-slate-600" data-doc-page-progress></span>
                <button type="button" data-doc-page-next class="rounded-xl bg-cherry-700 px-4 py-2 text-xs font-medium text-white hover:bg-cherry-800 disabled:cursor-not-allowed disabled:opacity-40">Next &rarr;</button>
            </div>
        </div>
        <div data-pdf-pages class="grid justify-items-center gap-4"></div>
        <div data-pdf-loading class="flex flex-col items-center gap-3 py-16 text-center text-sm text-slate-500">
            <span class="doc-spinner" aria-hidden="true"></span>
            <span>Loading document…</span>
        </div>
    </div>

    <aside class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:w-80 lg:shrink-0">
        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Comments</h3>
        <div data-comment-track class="relative mt-4">
            <div data-comment-empty class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No comments yet.</div>
        </div>
    </aside>
</div>
