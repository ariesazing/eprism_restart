<div
    data-pdf-review
    data-document-url="{{ $documentViewUrl }}"
    data-comments-url="{{ $commentsUrl }}"
    data-channel="submission.{{ $submission->id }}"
    data-can-create="{{ $canCreate ? '1' : '0' }}"
    data-can-edit-all="{{ $canEditAll ? '1' : '0' }}"
    data-current-user-id="{{ auth()->id() }}"
    class="grid gap-6 lg:grid-cols-[1fr,320px]"
>
    <div class="rounded-2xl bg-slate-200/60 p-4 shadow-sm ring-1 ring-slate-200">
        <div class="hidden mb-4 flex-wrap items-center justify-between gap-3" data-document-view-controls>
            <div class="flex gap-2">
                <button type="button" data-doc-view-mode="scroll" class="rounded-full px-4 py-2 text-xs font-medium">Scrollable</button>
                <button type="button" data-doc-view-mode="paginated" class="rounded-full px-4 py-2 text-xs font-medium">Paginated</button>
            </div>
            <div class="hidden items-center gap-3" data-doc-pagination-controls>
                <button type="button" data-doc-page-prev class="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">&larr; Previous</button>
                <span class="text-xs font-medium text-slate-600" data-doc-page-progress></span>
                <button type="button" data-doc-page-next class="rounded-full bg-red-700 px-4 py-2 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">Next &rarr;</button>
            </div>
        </div>
        <div data-pdf-pages class="grid justify-items-center gap-4"></div>
        <div data-pdf-loading class="py-16 text-center text-sm text-slate-500">Loading document…</div>
    </div>

    <aside class="h-fit rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:sticky lg:top-6 lg:flex lg:max-h-[calc(100vh-3rem)] lg:flex-col">
        <h3 class="shrink-0 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Comments</h3>
        <div data-comment-composer class="hidden mt-4 shrink-0"></div>
        <div data-comment-sidebar class="mt-4 grid gap-3 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pr-1">
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No comments yet.</div>
        </div>
    </aside>
</div>
