@php
    // Every leaf key this rubric scores, pre-filled from the just-failed submission (old()) or
    // this reviewer's own already-submitted evaluation, in that priority — matching how the rest
    // of this page's fields prefill. Deliberately from Review::$criteria_scores directly, never
    // from the submission's *current* rubric reinterpreting an old review scored under a
    // different one (see the rubric_key migration's own comment) — $existingReview and $rubric
    // always agree here regardless, since the controller resolves both from the same submission
    // at the same time.
    $initialScores = collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [
        $key => old($key, $existingReview->criteria_scores[$key] ?? null),
    ]);

    $recommendationLabels = ['approve' => 'Approve', 'minor_revision' => 'Minor Revision', 'major_revision' => 'Major Revision'];
    // No default recommendation — a reviewer must consciously pick one (see the placeholder
    // option in the modal below) rather than silently inheriting one by never touching the
    // field. An already-submitted review's own real value still prefills exactly as before.
    $initialRecommendation = old('recommendation', $existingReview->recommendation ?? '');
    // Once the submission is finalized, evaluations are locked (see the matching guard added
    // to storeReview()) — the round is over, so re-editing here would just re-fire
    // notification/routing-slip side effects for nothing.
    $isFinalized = $submission->status === \App\Enums\SubmissionStatus::APPROVED;
@endphp
{{--
    x-focus-layout (no sidebar) — reviewing/evaluating a submission is a dedicated task, not a
    page within the app's usual section-to-section browsing (same reasoning as
    submissions/document-review.blade.php). The header slot below carries its own "Back to
    queue" link and the "Start Evaluation" trigger next to the research details, so nothing
    reachable via the sidebar is lost.
--}}
<x-focus-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between" x-data>
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Researcher: {{ $submission->researcher?->name ?? 'Unknown researcher' }} · {{ $template->label }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                @unless ($isFinalized)
                    <button type="button" @click="$dispatch('open-modal', 'rubric-scoring')" class="inline-flex items-center gap-2 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">
                        <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ $existingReview?->submitted_at ? 'Update Evaluation' : 'Start Evaluation' }}
                    </button>
                @endunless
                <a href="{{ route('reviewer.submissions.index') }}" class="text-sm font-medium text-cherry-700">Back to queue</a>
            </div>
        </div>
    </x-slot>

    @vite(['resources/js/pdf-review.js', 'resources/js/submission-discussion.js'])

    @include('submissions.partials.discussion', [
        'submission' => $submission,
        'discussionUrl' => route('reviewer.submissions.discussion.index', $submission),
        'canDeleteAll' => false,
        'floating' => true,
    ])

    <div class="py-4">
        <div class="mx-auto grid w-full gap-6 px-4 sm:px-6">
            @if ($errors->any())
                <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($reviewSummary)
                <div class="rounded-2xl bg-emerald-50 p-5 shadow-sm ring-1 ring-emerald-200">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-emerald-900">This research was approved</h3>
                            <p class="mt-1 text-xs text-emerald-700">The generated Review Summary for this round is ready to preview.</p>
                        </div>
                        <a href="{{ route('rapm-documents.show', $reviewSummary) }}" target="_blank" class="shrink-0 rounded-xl bg-emerald-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-emerald-700">Preview Review Summary</a>
                    </div>
                </div>
            @endif

            <div class="min-w-0 border-b border-slate-200 pb-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Manuscript &amp; Comments</h3>
                        <p class="mt-1 text-sm text-slate-500">Review the full structured submission and leave sidebar comments without altering the document.</p>
                    </div>
                    @if ($submission->snapshots->count() > 1)
                        <div class="shrink-0" x-data="{ open: false }">
                            <button type="button" @click="open = ! open" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Other Versions</button>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-10 mt-2 grid gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                                @foreach ($submission->snapshots as $snapshot)
                                    <a href="{{ $loop->first ? '#' : route('reviewer.submissions.manuscript.version.review', [$submission, $snapshot]) }}" class="flex items-center justify-between gap-4 rounded-lg px-3 py-2 text-sm {{ $loop->first ? 'bg-cherry-50 text-cherry-700' : 'text-slate-700 hover:bg-slate-50' }}">
                                        <span>Version {{ $snapshot->version }}</span>
                                        @if ($loop->first)
                                            <span class="text-xs font-medium">Current</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-4">
                    @if ($submission->snapshots->isNotEmpty())
                        @include('submissions.partials.pdf-viewer', [
                            'submission' => $submission,
                            'documentViewUrl' => route('reviewer.submissions.manuscript', $submission),
                            'commentsUrl' => route('reviewer.submissions.comments.index', $submission),
                            'canCreate' => true,
                            'canEditAll' => false,
                            'snapshotId' => null,
                            'attachmentsDownloadRoute' => 'reviewer.submissions.attachments.download',
                        ])
                    @else
                        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No manuscript generated yet.</div>
                    @endif
                </div>
            </div>

            {{-- Only shown once finalized: the header's "Start/Update Evaluation" button already
                 covers the active state, and disappears once finalized — so this is the only
                 remaining way to see a reviewer's own recorded evaluation after the fact. --}}
            @if ($isFinalized && $existingReview)
                <div class="min-w-0 border-b border-slate-200 pb-6">
                    <h3 class="text-lg font-semibold text-slate-900">Your Evaluation</h3>
                    <div class="mt-4 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <p><span class="font-medium text-slate-900">Recommendation:</span> {{ $recommendationLabels[$existingReview->recommendation] ?? $existingReview->recommendation }}</p>
                        <p class="mt-1"><span class="font-medium text-slate-900">Score:</span> {{ $existingReview->totalScore() }} / {{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }}</p>
                        <p class="mt-2 whitespace-pre-wrap">{{ $existingReview->comments }}</p>
                        <p class="mt-3 text-xs text-slate-500">This submission has since been finalized, so evaluations are now locked.</p>
                    </div>
                </div>
            @endif

            @if ($peerReviews->isNotEmpty())
                <div class="min-w-0 border-b border-slate-200 pb-6">
                    <h3 class="text-lg font-semibold text-slate-900">Peer Evaluations</h3>
                    <p class="mt-1 text-sm text-slate-500">Visible now that you've submitted your own evaluation.</p>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ($peerReviews as $peerReview)
                            <div class="app-card-inset p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-slate-900">{{ $peerReview->reviewer->name }}</span>
                                    <x-recommendation-badge :recommendation="$peerReview->recommendation" />
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Score: {{ $peerReview->totalScore() }} / {{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }}</p>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ $peerReview->comments }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @unless ($isFinalized)
        <script>
            // Defined here, before Alpine starts (app.js is a deferred module), so
            // x-data="rubricScoring(...)" below can find it.
            //
            // `rubric` is App\Evaluation\RubricTemplate::toArray() — sections -> items -> (a
            // parent item's own) children, each item {key, code, label, max, children}. `scores`
            // holds only leaf values (a parent is never itself a key in here — see subtotal()); a
            // leaf's own initial value already carries old()/the existing review's prefill from
            // the server, so this never needs to re-derive it.
            window.rubricScoring = (rubric, initialScores, initialRecommendation, recommendationLabels) => ({
                rubric,
                scores: { ...initialScores },
                recommendation: initialRecommendation,
                recommendationLabels,

                get total() {
                    return Object.values(this.scores).reduce((sum, value) => sum + (Number(value) || 0), 0);
                },

                // A leaf's own score, or (for a parent) its children's scores summed — recurses so
                // a parent-of-parents would still work, even though nothing today nests that deep.
                subtotal(item) {
                    if (! item.children.length) {
                        return Number(this.scores[item.key]) || 0;
                    }

                    return item.children.reduce((sum, child) => sum + this.subtotal(child), 0);
                },

                get allScored() {
                    return Object.keys(this.scores).every((key) => this.scores[key] !== null && this.scores[key] !== '');
                },
            });
        </script>

        {{--
            One shared x-data wraps both modals (not one each) — confirm-evaluation's own
            summary (total/recommendation) reads the exact same reactive state the rubric form
            itself is mutating, the same way a single Alpine scope let the original inline
            layout do this. The confirm button below still submits the form via a plain
            getElementById rather than $refs — $refs only resolves within one component's own
            ancestor chain, and the form and that button now sit in separate sibling modals.

            The scoring fieldset itself is rendered entirely by Alpine (`rubricScoring()` above),
            not a Blade @foreach — the four rubrics' section/item tree (@js($rubric->toArray()))
            is already exactly what the JS needs, so templating it a second time in Blade would
            just be the same structure maintained twice. Nesting is hardcoded two levels deep
            (item, then item.children) rather than written generically/recursively: every rubric
            ResearchEvaluationRubric defines today is at most two levels, and Alpine's
            <template x-for> has no clean way to recurse arbitrarily deep anyway — if a future
            rubric ever needs a third level, this markup (not just the PHP side) needs revisiting.
        --}}
        <div
            x-data="rubricScoring(@js($rubric->toArray()), @js($initialScores), @js($initialRecommendation), @js($recommendationLabels))"
        >
        {{-- Auto-opens on a failed submission (e.g. a missing/out-of-range score, see
             storeReview()'s own validate() call) — the form itself lives only in this modal
             now, so a plain page-top error banner would leave the actual error hidden behind a
             closed modal. --}}
        <x-modal name="rubric-scoring" max-width="4xl" focusable :show="$errors->any()">
            <div class="max-h-[85vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3"><button type="button" @click="$dispatch('close-modal', 'rubric-scoring')" aria-label="Close rubric scoring" class="rounded-lg px-3 py-2 hover:bg-slate-100">&times;</button><h3 class="text-lg font-semibold text-slate-900">{{ $rubric->label }}</h3></div>
                    <div class="text-right">
                        <div class="text-2xl font-semibold text-slate-800" x-text="total + ' / {{ $rubric->max() }}'"></div>
                        <div class="text-xs text-slate-500">Scoring is informational — recommendation is your own call</div>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($existingReview?->submitted_at)
                    <div class="mt-3 rounded-xl bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                        You submitted this evaluation {{ $existingReview->submitted_at->diffForHumans() }}. You may still update it until this round is finalized.
                    </div>
                @endif

                <form id="evaluation-form" method="POST" action="{{ route('reviewer.submissions.review', $submission) }}" class="mt-4 grid gap-6">
                    @csrf
                    <div class="grid gap-5">
                        <template x-for="section in rubric.sections" :key="section.label ?? '_'">
                            <fieldset class="rounded-xl border border-slate-200 p-4">
                                <legend x-show="section.label" class="px-1 text-sm font-semibold uppercase tracking-wide text-slate-500" x-text="section.label + ' (' + section.max + ' points)'"></legend>
                                <div class="grid gap-3">
                                    <template x-for="item in section.items" :key="item.key ?? item.code">
                                        <div class="rounded-lg border border-slate-200 p-3" :class="item.children.length ? 'bg-slate-50' : ''">
                                            <div class="flex items-center justify-between gap-3">
                                                <label class="text-sm font-medium text-slate-800" :for="item.key" x-text="item.code + '. ' + item.label + ' (' + item.max + ' pts)'"></label>

                                                {{-- Leaf: an actual score input. --}}
                                                <template x-if="! item.children.length">
                                                    <input
                                                        type="number"
                                                        :id="item.key"
                                                        :name="item.key"
                                                        min="0"
                                                        :max="item.max"
                                                        step="1"
                                                        x-model.number="scores[item.key]"
                                                        class="w-20 shrink-0 rounded-lg border-slate-300 text-right text-sm"
                                                        required
                                                    />
                                                </template>

                                                {{-- Parent: read-only, always the sum of its own children below. --}}
                                                <template x-if="item.children.length">
                                                    <span class="shrink-0 text-sm font-semibold text-slate-700" x-text="subtotal(item) + ' / ' + item.max"></span>
                                                </template>
                                            </div>

                                            <template x-if="item.children.length">
                                                <div class="mt-2 grid gap-2 border-t border-slate-200 pt-2 pl-4">
                                                    <template x-for="child in item.children" :key="child.key">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <label class="text-xs text-slate-600" :for="child.key" x-text="child.code + '. ' + child.label + ' (' + child.max + ' pts)'"></label>
                                                            <input
                                                                type="number"
                                                                :id="child.key"
                                                                :name="child.key"
                                                                min="0"
                                                                :max="child.max"
                                                                step="1"
                                                                x-model.number="scores[child.key]"
                                                                class="w-20 shrink-0 rounded-lg border-slate-300 text-right text-xs"
                                                                required
                                                            />
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </fieldset>
                        </template>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Overall Comment</label>
                        <textarea name="comments" rows="6" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('comments', $existingReview->comments ?? '') }}</textarea>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Recommendation</label>
                        <select name="recommendation" x-model="recommendation" class="mt-2 w-full rounded-xl border-slate-300" required>
                            <option value="" disabled>Choose recommendation</option>
                            @foreach ($recommendationLabels as $value => $label)
                                <option value="{{ $value }}" @selected($initialRecommendation === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <button type="button" :disabled="! allScored || ! recommendation" @click="if (document.getElementById('evaluation-form').reportValidity()) { $dispatch('close-modal', 'rubric-scoring'); $dispatch('open-modal', 'confirm-evaluation') }" class="rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $existingReview?->submitted_at ? 'Update Evaluation' : 'Submit Evaluation' }}</button>
                        <p class="mt-2 text-xs text-amber-600" x-show="! allScored" x-cloak>Score every criterion above before continuing.</p>
                        <p class="mt-2 text-xs text-amber-600" x-show="allScored && ! recommendation" x-cloak>Choose a recommendation before continuing.</p>
                    </div>
                </form>
            </div>
        </x-modal>

        <x-modal name="confirm-evaluation" max-width="md" focusable>
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Confirm your evaluation</h3>
                <p class="mt-3 text-sm text-slate-600">Score: <span class="font-semibold text-slate-900" x-text="total + ' / {{ $rubric->max() }}'"></span></p>
                <p class="mt-1 text-sm text-slate-600">Recommendation: <span class="font-semibold text-slate-900" x-text="recommendationLabels[recommendation] ?? recommendation"></span></p>

                <p class="mt-3 text-xs text-slate-500">This will be recorded as your evaluation for this submission{{ $existingReview?->submitted_at ? ', replacing your previous one' : '' }}. Double-check your scoring, comment, and recommendation before confirming.</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="$dispatch('close-modal', 'confirm-evaluation'); $dispatch('open-modal', 'rubric-scoring')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    {{-- A plain getElementById, not $refs.form — the form and this button now
                         live in separate modals (sibling Alpine components under the shared
                         x-data above), and $refs only resolves within one component's own
                         ancestor chain, not across sibling branches. --}}
                    <button type="button" @click="$dispatch('close-modal', 'confirm-evaluation'); $dispatch('close-modal', 'rubric-scoring'); submitWithFeedback(document.getElementById('evaluation-form'), { successMessage: 'Successfully submitted!' })" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">Confirm &amp; Submit</button>
                </div>
            </div>
        </x-modal>
        </div>

        @include('components.submit-feedback-modal')
    @endunless
</x-focus-layout>
