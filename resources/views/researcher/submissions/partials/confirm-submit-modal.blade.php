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
    $sramData = $sram ?? $submission->readinessAssessment?->metrics ?? app(\App\Services\SubmissionAssessmentService::class)->assess($submission);
    $flaggedChapters = $sramData['flagged_chapters'] ?? ($sramData['metrics']['flagged_chapters'] ?? []);
    $hasFlagged = count($flaggedChapters) > 0;
@endphp

<x-modal :name="$modalName" max-width="lg" focusable>
    <div class="p-6" x-data="{ flaggedAck: {{ $hasFlagged ? 'false' : 'true' }} }">
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
                <dt class="shrink-0 text-slate-500">SRAM Completeness</dt>
                <dd class="text-right font-medium text-slate-900">{{ $sramData['completeness_percent'] ?? 100 }}%</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-slate-500">SRAM Grammar Score</dt>
                <dd class="text-right font-medium text-slate-900">
                    @if (isset($sramData['grammar_percent']) && $sramData['grammar_percent'] !== null)
                        {{ number_format($sramData['grammar_percent'], 1) }}%
                    @else
                        <span class="text-slate-500">Not checked yet</span>
                    @endif
                </dd>
            </div>
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

        @if ($hasFlagged)
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
                <div class="flex items-start gap-2.5">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div class="text-xs text-rose-900">
                        <p class="font-semibold text-rose-950">Notice: Flagged Short Chapters</p>
                        <p class="mt-1">The following {{ count($flaggedChapters) }} chapter(s) contain fewer than 10 words:</p>
                        <ul class="mt-1.5 list-disc list-inside space-y-0.5 font-medium">
                            @foreach ($flaggedChapters as $ch)
                                <li>{{ $ch['label'] }} ({{ $ch['word_count'] }} {{ $ch['word_count'] === 1 ? 'word' : 'words' }})</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <label class="mt-3 flex items-start gap-2.5 rounded-lg border border-rose-300 bg-white/80 p-2.5 text-xs text-rose-950 cursor-pointer hover:bg-white transition">
                    <input type="checkbox" x-model="flaggedAck" class="mt-0.5 rounded border-rose-400 text-cherry-700 focus:ring-cherry-600">
                    <span>I acknowledge that the flagged chapter(s) have fewer than 10 words and confirm that I want to proceed with submission.</span>
                </label>
            </div>
        @endif

        <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
            Once {{ $resubmit ? 'resubmitted' : 'submitted' }}, this research becomes read-only while it's under review. You won't be able to change its chapters or attachments unless it's returned to you for revisions.
        </p>

        <div class="mt-5 flex justify-end gap-3">
            <button type="button" @click="$dispatch('close-modal', '{{ $modalName }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            @if ($viaFetch)
                <button type="button" :disabled="!flaggedAck" :class="{ 'opacity-50 cursor-not-allowed': !flaggedAck }" @click="if (flaggedAck) { $dispatch('close-modal', '{{ $modalName }}'); submitWithFeedback(document.getElementById('{{ $formId }}'), { successMessage: 'Successfully submitted!' }); }" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800 transition">Confirm &amp; {{ $verb }}</button>
            @else
                <button type="button" :disabled="!flaggedAck" :class="{ 'opacity-50 cursor-not-allowed': !flaggedAck }" @click="if (flaggedAck) { $dispatch('close-modal', '{{ $modalName }}'); document.getElementById('{{ $formId }}').requestSubmit(); }" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800 transition">Confirm &amp; {{ $verb }}</button>
            @endif
        </div>
    </div>
</x-modal>
