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
        <div data-pdf-pages class="grid justify-items-center gap-4"></div>
        <div data-pdf-loading class="py-16 text-center text-sm text-slate-500">Loading document…</div>
    </div>

    <aside class="h-fit rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:sticky lg:top-6">
        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Comments</h3>
        <p class="mt-1 text-xs text-slate-400">Comments never modify the document — they're shown here alongside it.</p>
        <div data-comment-sidebar class="mt-4 grid gap-3">
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No comments yet.</div>
        </div>
    </aside>
</div>
