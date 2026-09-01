<x-app-layout skeleton="form">
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Researcher: {{ $submission->researcher->name }} · {{ $template->label }}</p>
            </div>
            <a href="{{ route('reviewer.submissions.index') }}" class="text-sm font-medium text-cherry-700">Back to queue</a>
        </div>
    </x-slot>

    @vite(['resources/js/pdf-review.js', 'resources/js/submission-discussion.js'])

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
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

            <div class="flex justify-end">
                @include('submissions.partials.discussion', [
                    'submission' => $submission,
                    'discussionUrl' => route('reviewer.submissions.discussion.index', $submission),
                    'canDeleteAll' => false,
                ])
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
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
                        ])
                    @else
                        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No manuscript generated yet.</div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Attachments</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($submission->documents as $document)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
                            <a href="{{ route('reviewer.submissions.attachments.download', [$submission, $document]) }}" class="hover:underline">{{ $document->original_name }}</a>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500 sm:col-span-2 lg:col-span-3">No attachments uploaded.</div>
                    @endforelse
                </div>
            </div>

            @php
                $existingTiers = collect($existingReview->criteria_scores ?? [])->map(fn ($c) => $c['tier'] ?? null);

                $criteriaPoints = collect(\App\Evaluation\ResearchEvaluationRubric::CRITERIA)
                    ->map(fn ($criterion) => collect($criterion['tiers'])->mapWithKeys(fn ($tier, $tierKey) => [$tierKey => $tier['points']]));

                $initialPoints = collect(\App\Evaluation\ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(function ($key) use ($existingTiers) {
                    $tier = old($key, $existingTiers[$key] ?? null);

                    return [$key => $tier ? \App\Evaluation\ResearchEvaluationRubric::pointsFor($key, $tier) : 0];
                });

                $recommendationLabels = ['approve' => 'Approve', 'minor_revision' => 'Minor Revision', 'major_revision' => 'Major Revision'];
                $initialRecommendation = old('recommendation', $existingReview->recommendation ?? 'minor_revision');
                // Once the submission is finalized, evaluations are locked (see the matching
                // guard added to storeReview()) — the round is over, so re-editing here would
                // just re-fire notification/routing-slip side effects for nothing.
                $isFinalized = $submission->status === \App\Enums\SubmissionStatus::APPROVED;
            @endphp

            <div
                class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
                x-data="{
                    points: @js($initialPoints),
                    tables: @js($criteriaPoints),
                    recommendation: @js($initialRecommendation),
                    setPoints(criterion, tier) { this.points[criterion] = this.tables[criterion][tier] ?? 0 },
                    get total() { return Object.values(this.points).reduce((a, b) => a + b, 0) },
                    get passes() { return this.total >= {{ \App\Evaluation\ResearchEvaluationRubric::PASSING_SCORE }} },
                    recommendationLabel() {
                        return ({ approve: 'Approve', minor_revision: 'Minor Revision', major_revision: 'Major Revision' })[this.recommendation] ?? this.recommendation;
                    },
                }"
            >
                <div class="flex items-center justify-between gap-4">
                    <h3 class="text-lg font-semibold text-slate-900">Rubric Scoring</h3>
                    @unless ($isFinalized)
                        <div class="text-right">
                            <div class="text-2xl font-semibold" :class="passes ? 'text-emerald-600' : 'text-amber-600'" x-text="total + ' / {{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }}'"></div>
                            <div class="text-xs text-slate-500">Needs {{ \App\Evaluation\ResearchEvaluationRubric::PASSING_SCORE }}/{{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }} to approve</div>
                        </div>
                    @endunless
                </div>

                @if ($existingReview?->submitted_at)
                    <div class="mt-3 rounded-xl bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                        You submitted this evaluation {{ $existingReview->submitted_at->diffForHumans() }}.
                        @if ($isFinalized)
                            This submission has since been finalized, so evaluations are now locked.
                        @else
                            You may still update it until this round is finalized.
                        @endif
                    </div>
                @endif

                @if ($isFinalized && $existingReview)
                    <div class="mt-4 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <p><span class="font-medium text-slate-900">Recommendation:</span> {{ $recommendationLabels[$existingReview->recommendation] ?? $existingReview->recommendation }}</p>
                        <p class="mt-1"><span class="font-medium text-slate-900">Score:</span> {{ $existingReview->totalScore() }} / {{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }}</p>
                        <p class="mt-2 whitespace-pre-wrap">{{ $existingReview->comments }}</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('reviewer.submissions.review', $submission) }}" class="mt-4 grid gap-6" x-ref="form">
                        @csrf
                        <div class="grid gap-4">
                            @foreach (\App\Evaluation\ResearchEvaluationRubric::CRITERIA as $key => $criterion)
                                <fieldset class="rounded-xl border border-slate-200 p-4">
                                    <legend class="px-1 text-sm font-semibold text-slate-800">{{ $criterion['label'] }} ({{ $criterion['weight'] }}%)</legend>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                        @foreach ($criterion['tiers'] as $tierKey => $tier)
                                            <label class="flex cursor-pointer flex-col gap-1 rounded-xl border border-slate-200 p-3 text-xs has-[:checked]:border-cherry-500 has-[:checked]:bg-cherry-50">
                                                <span class="flex items-center justify-between">
                                                    <span class="font-medium capitalize text-slate-800">{{ $tierKey }}</span>
                                                    <input
                                                        type="radio"
                                                        name="{{ $key }}"
                                                        value="{{ $tierKey }}"
                                                        x-on:change="setPoints('{{ $key }}', '{{ $tierKey }}')"
                                                        @checked(old($key, $existingTiers[$key] ?? null) === $tierKey)
                                                        required
                                                    />
                                                </span>
                                                <span class="text-slate-500">{{ $tier['description'] }}</span>
                                                <span class="font-semibold text-slate-700">{{ $tier['points'] }} pts</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Recommendation</label>
                            <select name="recommendation" x-model="recommendation" class="mt-2 w-full rounded-xl border-slate-300">
                                @foreach ($recommendationLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($initialRecommendation === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500" x-show="! passes">Approve is only accepted once the total score reaches {{ \App\Evaluation\ResearchEvaluationRubric::PASSING_SCORE }}.</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Overall Comment</label>
                            <textarea name="comments" rows="6" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('comments', $existingReview->comments ?? '') }}</textarea>
                        </div>

                        <button type="button" @click="$dispatch('open-modal', 'confirm-evaluation')" class="rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">{{ $existingReview?->submitted_at ? 'Update Evaluation' : 'Submit Evaluation' }}</button>
                    </form>

                    <x-modal name="confirm-evaluation" max-width="md">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Confirm your evaluation</h3>
                            <p class="mt-3 text-sm text-slate-600">Recommendation: <span class="font-semibold text-slate-900" x-text="recommendationLabel()"></span></p>
                            <p class="mt-1 text-sm text-slate-600">Score: <span class="font-semibold text-slate-900" x-text="total + ' / {{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }}'"></span></p>
                            <p class="mt-3 text-xs text-slate-500">This will be recorded as your evaluation for this submission{{ $existingReview?->submitted_at ? ', replacing your previous one' : '' }}. Double-check your scoring and comment before confirming.</p>
                            <div class="mt-5 flex justify-end gap-3">
                                <button type="button" @click="$dispatch('close-modal', 'confirm-evaluation')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="button" @click="$dispatch('close-modal', 'confirm-evaluation'); $refs.form.requestSubmit()" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">Confirm &amp; Submit</button>
                            </div>
                        </div>
                    </x-modal>
                @endif
            </div>

            @if ($peerReviews->isNotEmpty())
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Peer Evaluations</h3>
                    <p class="mt-1 text-sm text-slate-500">Visible now that you've submitted your own evaluation.</p>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ($peerReviews as $peerReview)
                            <div class="rounded-2xl bg-slate-50 p-4">
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
</x-app-layout>
