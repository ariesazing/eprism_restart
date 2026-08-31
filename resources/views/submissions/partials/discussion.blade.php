{{--
    Self-contained collapsible chat modal (not the shared <x-modal> component): the admin
    usage opens this from inside the submission-details modal, and that component's content
    wrapper carries a static `transform` class, which creates a new CSS containing block for
    any `position: fixed` descendant — nesting <x-modal> in there would render mispositioned.
    x-teleport="body" sidesteps that by moving the overlay to a direct child of <body>
    regardless of where the trigger lives in the DOM, so this one markup works for both the
    reviewer's standalone page and the admin's nested-modal usage.
--}}
<div x-data="{ open: false }">
    <button
        type="button"
        @click="open = true; window.initSubmissionDiscussion?.(document.getElementById('discussion-{{ $submission->id }}'))"
        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
    >
        <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        Reviewer Discussion
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-on:keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-end justify-center px-4 py-6 sm:items-center"
            style="display: none;"
        >
            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="open = false"
                class="fixed inset-0 bg-slate-900/60"
            ></div>

            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative flex max-h-[80vh] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 sm:max-w-lg"
            >
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Reviewer Discussion</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Private to assigned reviewers and admin — the researcher can't see this.</p>
                    </div>
                    <button type="button" @click="open = false" class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <div
                        id="discussion-{{ $submission->id }}"
                        data-discussion
                        data-messages-url="{{ $discussionUrl }}"
                        data-channel="submission.{{ $submission->id }}.discussion"
                        data-current-user-id="{{ auth()->id() }}"
                        data-current-user-name="{{ auth()->user()->name }}"
                        data-can-delete-all="{{ ($canDeleteAll ?? false) ? '1' : '0' }}"
                    >
                        <div data-discussion-messages class="flex max-h-96 flex-col gap-3 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4"></div>
                        <div data-discussion-empty class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No messages yet — start the discussion.</div>

                        <form data-discussion-form data-no-progress class="mt-3 flex items-end gap-3">
                            <textarea data-discussion-input rows="2" placeholder="Message the other reviewers and admin…" class="flex-1 rounded-xl border-slate-300 text-sm" required></textarea>
                            <button type="submit" class="shrink-0 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
