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
         rather than jumping nowhere.

         Collapsible (defaults open) so a reviewer can reclaim the document's own width on a
         narrower screen or a long chapter list — collapsing only hides the list itself, the
         toggle stays put as a slim rail rather than the whole column vanishing and reflowing.

         Attachments share this same rail (rather than their own separate section elsewhere on
         the page) when the includer passes $attachmentsDownloadRoute — a plain route *name*,
         since this partial is shared across researcher/reviewer/admin pages that each need
         their own role-scoped download route for the same $document. --}}
    @php
        $showAttachments = ! empty($attachmentsDownloadRoute) && $submission->documents->isNotEmpty();
    @endphp
    @if (! empty($chapterSections) || $showAttachments)
        <div class="lg:sticky lg:top-4 lg:max-h-[calc(100dvh-2rem)] lg:shrink-0 lg:overflow-y-auto" x-data="{ chapterNavOpen: true }" :class="chapterNavOpen ? 'lg:w-56' : 'lg:w-auto'">
            <button
                type="button"
                @click="chapterNavOpen = ! chapterNavOpen"
                aria-label="Toggle chapter navigation" :aria-expanded="chapterNavOpen" class="mb-2 flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-cherry-700 lg:flex"
                :class="chapterNavOpen ? 'justify-between' : 'justify-center'"
            >
                <span x-show="chapterNavOpen">Chapters</span>
                <svg class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': ! chapterNavOpen }" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
            </button>
            <div x-show="chapterNavOpen" class="flex flex-col gap-4">
                @if (! empty($chapterSections))
                    <div class="flex flex-wrap gap-2 bg-slate-100/95 py-2 backdrop-blur lg:top-4 lg:flex-col lg:flex-nowrap lg:bg-transparent lg:py-0 lg:backdrop-blur-none" data-chapter-nav>
                        @foreach ($chapterSections as $index => $definition)
                            <button type="button" data-chapter-jump="{{ $definition->key }}" class="rounded-xl border border-slate-300 px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:border-cherry-300 hover:text-cherry-700 disabled:cursor-not-allowed disabled:opacity-40 lg:w-full">
                                {{ $index + 1 }}. {{ $definition->label }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($showAttachments)
                    <div>
                        <p class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Attachments</p>
                        <div class="flex flex-col gap-2">
                            @foreach ($submission->documents as $document)
                                <a href="{{ route($attachmentsDownloadRoute, [$submission, $document]) }}" class="truncate rounded-xl border border-slate-200 px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:border-cherry-300 hover:text-cherry-700 lg:w-full" title="{{ $document->original_name }}">
                                    {{ $document->original_name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
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

    <aside class="w-full border-t border-slate-200 p-4 lg:w-72 lg:shrink-0 lg:border-l lg:border-t-0">
        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Comments</h3>
        <div data-comment-track class="relative mt-4">
            <div data-comment-empty class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No comments yet.</div>
        </div>
    </aside>
</div>
