<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-800">Reviewer history</h2></x-slot>
    <div class="mx-auto grid min-w-0 max-w-6xl grid-cols-1 gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0"><h3 class="break-words text-xl font-bold text-slate-900">{{ $reviewer->name }}</h3><p class="break-all text-sm text-slate-500">{{ $reviewer->email }}</p></div>
            <nav class="flex gap-4 text-sm font-medium text-blue-700" aria-label="Reviewer history navigation">
                <a class="hover:underline" href="{{ route('admin.reports') }}">Reviewer tracking</a>
                <a class="hover:underline" href="{{ route('admin.users.index', ['role' => 'reviewer']) }}">User management</a>
            </nav>
        </div>
        <dl class="grid gap-4 sm:grid-cols-3">
            @foreach (['Active assignments' => $activeCount, 'Submitted evaluations' => $completedCount, 'Studies with evaluations' => $researchCount] as $label => $value)
                <div class="app-card bg-white p-4"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <section class="app-card min-w-0 bg-white p-5" aria-labelledby="assigned-research-heading">
            <h3 id="assigned-research-heading" class="text-lg font-semibold text-slate-900">Assigned research</h3>
            <p class="mt-1 text-sm text-slate-500">Research still linked to this reviewer, including completed assignments.</p>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($assignments as $submission)
                    @php
                        $review = $submission->reviews->sortByDesc('updated_at')->first();
                        $deadline = $submission->pivot->deadline_at ? \Carbon\Carbon::parse($submission->pivot->deadline_at) : null;
                        $active = in_array($submission->status->value, \App\Services\ReviewerWorkload::ACTIVE_STATUSES, true);
                        $progress = $review?->submitted_at ? 'completed' : ($active && $deadline?->isPast() ? 'overdue' : ($review ? 'in_progress' : 'not_started'));
                    @endphp
                    <article class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <div class="min-w-0"><p class="font-mono text-xs text-slate-500">{{ $submission->reference_code }}</p><a href="{{ route('admin.submissions.index', ['search' => $submission->reference_code ?: $submission->title]) }}" class="break-words font-semibold text-blue-700 hover:underline">{{ $submission->title }}</a>
                            <p class="mt-1 text-xs text-slate-500">Assigned {{ $submission->pivot->created_at?->format('M j, Y g:i A') ?? 'date not recorded' }} &middot; Deadline {{ $deadline?->format('M j, Y g:i A') ?? 'not set' }}</p>
                        </div>
                        <div class="flex flex-wrap items-start gap-2"><x-status-badge :status="$submission->status" /><x-status-badge :status="$progress" /></div>
                    </article>
                @empty
                    <p class="py-6 text-sm text-slate-500">No research is currently assigned to this reviewer.</p>
                @endforelse
            </div>
            {{ $assignments->links() }}
        </section>
        <section class="app-card min-w-0 bg-white p-5" aria-labelledby="evaluation-history-heading">
            <h3 id="evaluation-history-heading" class="text-lg font-semibold text-slate-900">Evaluation history</h3>
            <p class="mt-1 text-sm text-slate-500">Saved and submitted evaluations across manuscript versions, including research no longer assigned to this reviewer.</p>
            <div class="mt-4 space-y-3">
                @forelse ($evaluations as $evaluation)
                    <details class="rounded-xl border border-slate-200 p-4">
                        <summary class="cursor-pointer break-words text-sm text-slate-800">
                            <span class="font-semibold">{{ $evaluation->submission?->title ?? 'Research unavailable' }}</span>
                            <span class="ml-2 text-xs text-slate-500">{{ $evaluation->submission?->reference_code }} &middot; {{ $evaluation->manuscriptVersion?->snapshot ? 'Version '.$evaluation->manuscriptVersion->snapshot->version : 'Version not recorded' }}</span>
                            <span class="mt-2 flex flex-wrap items-center gap-2"><x-status-badge :status="$evaluation->submitted_at ? 'completed' : 'in_progress'" /><span class="text-xs text-slate-500">{{ $evaluation->submitted_at ? 'Submitted '.$evaluation->submitted_at->format('M j, Y g:i A') : 'Last saved '.$evaluation->updated_at->format('M j, Y g:i A') }}</span></span>
                        </summary>
                        <dl class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2">
                            <div><dt class="font-medium text-slate-500">Recommendation{{ $evaluation->submitted_at ? '' : ' (draft)' }}</dt><dd>{{ $evaluation->recommendation ? str($evaluation->recommendation)->replace('_', ' ')->headline() : 'Not recorded' }}</dd></div>
                            <div><dt class="font-medium text-slate-500">Score{{ $evaluation->submitted_at ? '' : ' (draft)' }}</dt><dd>{{ $evaluation->criteria_scores ? $evaluation->totalScore().' / '.\App\Evaluation\ResearchEvaluationRubric::MAX_SCORE : 'Not scored' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="font-medium text-slate-500">Comments</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $evaluation->comments ?: 'No comments recorded.' }}</dd></div>
                        </dl>
                    </details>
                @empty
                    <p class="py-6 text-sm text-slate-500">No evaluations have been saved by this reviewer yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $evaluations->links() }}</div>
        </section>
    </div>
</x-app-layout>
