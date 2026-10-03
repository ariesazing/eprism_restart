@if (!empty($submission->evaluation_results))
    <section class="app-card bg-white p-6" aria-label="Completed evaluation results">
        <h3 class="text-lg font-semibold text-slate-900">Evaluation results</h3>
        @foreach (array_reverse($submission->evaluation_results) as $result)
            <details class="mt-4 rounded-xl border border-slate-200 p-4" @if ($loop->first) open @endif>
                <summary class="cursor-pointer font-medium text-slate-900">
                    {{ ucfirst($result['classification']) }} &middot; {{ str($result['outcome'])->replace('_', ' ')->headline() }}
                    &middot; Average: {{ number_format($result['average'], 2) }}%
                </summary>
                @if ($loop->first && $submission->status === \App\Enums\SubmissionStatus::REVISIONS_REQUIRED)
                    <p class="mt-3 text-sm text-slate-600">All assigned reviewers have finished. Your research is now editable; revise it using the feedback below and resubmit.</p>
                @endif
                @foreach ($result['reviews'] as $evaluation)
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <p class="text-sm font-semibold">Reviewer {{ $evaluation['reviewer_number'] }} &middot; {{ number_format($evaluation['score'], 2) }}% &middot; {{ str($evaluation['result'])->replace('_', ' ')->headline() }}</p>
                        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ $evaluation['comments'] }}</p>
                    </div>
                @endforeach
            </details>
        @endforeach
    </section>
@endif
