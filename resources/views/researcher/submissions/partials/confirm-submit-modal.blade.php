{{--
    "Are you sure?" step in front of a researcher's Submit / Resubmit — sending a draft to the
    reviewer queue locks it (see ResearchSubmission::isLocked()), so it's confirmed once more
    with a summary of exactly what's being sent, rather than firing on a single click. Only
    ever opened when the submission is ready; the "isn't ready yet" case has its own blocking
    modal (submission-incomplete) instead.

    Expects: $submission, $template, $readiness, $latestSimilarityCheck (?SimilarityCheck),
    $modalName, $formId (the <form> to submit on confirm), $resubmit (bool).
    Optional: $viaFetch (default true) — submit through submitWithFeedback()'s spinner/checkmark
    overlay (the per-chapter page) instead of a plain form post (the manuscript page).
--}}
@php
    $latest = $latestSimilarityCheck ?? null;
    $viaFetch = $viaFetch ?? true;
    $verb = $resubmit ? 'Resubmit' : 'Submit';
@endphp

<x-modal :name="$modalName" max-width="lg" focusable>
    <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900">{{ $resubmit ? 'Resubmit this revision for review?' : 'Submit this research for review?' }}</h3>
        <p class="mt-2 text-sm text-slate-600">Please confirm you're ready to send this to the reviewers.</p>

        <dl class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200">
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-slate-500">Title</dt>
                <dd class="text-right font-medium text-slate-900">{{ $submission->title }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-slate-500">Reference</dt>
                <dd class="font-mono text-slate-900">{{ $submission->reference_code }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-slate-500">Template</dt>
                <dd class="text-right text-slate-900">{{ $template->label }}</dd>
            </div>
            @if ($readiness['unavailable'] ?? false)
                {{-- A whole-manuscript readiness check that couldn't run right now isn't the
                     same as "0 of N complete" — it's re-run when the submission is confirmed. --}}
                <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-slate-500">Chapters &amp; attachments</dt>
                    <dd class="text-right text-slate-900">Checked when you confirm</dd>
                </div>
            @else
                <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-slate-500">Required chapters</dt>
                    <dd class="text-slate-900">{{ $readiness['sections']['done'] }} of {{ $readiness['sections']['total'] }} complete</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-slate-500">Required attachments</dt>
                    <dd class="text-slate-900">{{ $readiness['attachments']['done'] }} of {{ $readiness['attachments']['total'] }} uploaded</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-slate-500">Similarity check</dt>
                <dd class="text-right text-slate-900">
                    @if ($latest?->isCompleted())
                        <span class="font-medium">{{ number_format($latest->score, 0) }}%</span>
                        <a href="{{ route('submissions.similarity.show', [$submission, $latest]) }}" target="_blank" rel="noopener" class="ml-1 text-cherry-700 underline hover:no-underline">View report</a>
                    @elseif ($latest?->isActive())
                        <span class="text-slate-500">In progress</span>
                    @else
                        <span class="text-slate-500">Not run yet</span>
                        <a href="#similarity-check" @click="$dispatch('close-modal', '{{ $modalName }}')" class="ml-1 text-cherry-700 underline hover:no-underline">Run one first</a>
                    @endif
                </dd>
            </div>
        </dl>

        <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Once {{ $resubmit ? 'resubmitted' : 'submitted' }}, this research becomes read-only while it's under review. You won't be able to change its chapters or attachments unless it's returned to you for revisions.
        </p>

        <div class="mt-5 flex justify-end gap-3">
            <button type="button" @click="$dispatch('close-modal', '{{ $modalName }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            @if ($viaFetch)
                <button type="button" @click="$dispatch('close-modal', '{{ $modalName }}'); submitWithFeedback(document.getElementById('{{ $formId }}'), { successMessage: 'Successfully submitted!' })" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">Confirm &amp; {{ $verb }}</button>
            @else
                <button type="button" @click="$dispatch('close-modal', '{{ $modalName }}'); document.getElementById('{{ $formId }}').requestSubmit()" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">Confirm &amp; {{ $verb }}</button>
            @endif
        </div>
    </div>
</x-modal>
