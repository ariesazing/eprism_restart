@php
    $currentReviews = $submission->reviews->sortByDesc('updated_at')->unique('reviewer_id');
    $assignedReviews = $currentReviews->whereIn('reviewer_id', $submission->reviewers->pluck('id'));
    $completed = $assignedReviews->whereNotNull('submitted_at')->count();
    $assigned = $submission->reviewers->count();
    $percentage = $assigned ? round($completed / $assigned * 100) : 0;
    $events = collect();
    if ($submission->submitted_at) $events->push(['date' => $submission->submitted_at, 'label' => 'Research submitted']);
    foreach ($submission->reviewers as $person) {
        if ($person->pivot->created_at) $events->push(['date' => $person->pivot->created_at, 'label' => $person->name.' assigned']);
        $evaluation = $assignedReviews->firstWhere('reviewer_id', $person->id);
        if ($evaluation) $events->push(['date' => $evaluation->created_at, 'label' => $person->name.' saved an evaluation']);
        if ($evaluation?->submitted_at) $events->push(['date' => $evaluation->submitted_at, 'label' => $person->name.' completed evaluation']);
    }
    if ($submission->approved_at) $events->push(['date' => $submission->approved_at, 'label' => 'Research approved']);
@endphp
<section class="mt-6 space-y-5" aria-label="Evaluation monitoring">
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
        <div class="flex items-center justify-between gap-3">
            <h4 class="font-semibold text-slate-900">Evaluation progress</h4>
            <span class="text-sm font-semibold text-blue-800">{{ $completed }} / {{ $assigned }} complete</span>
        </div>
        <div role="progressbar" aria-label="Completed assigned evaluations" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentage }}" class="mt-3 h-3 overflow-hidden rounded-full bg-blue-100">
            <div class="h-full rounded-full bg-blue-600" style="width: {{ $percentage }}%"></div>
        </div>
        <p class="mt-2 text-xs text-slate-600">{{ $assigned ? $percentage.'% of assigned reviewers have submitted their current evaluation.' : 'Awaiting reviewer assignment.' }} Progress reflects saved activity, not time spent reading.</p>
    </div>
    <div>
        <h4 class="font-semibold text-slate-900">Reviewer monitoring</h4>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @forelse ($submission->reviewers as $person)
                @php
                    $evaluation = $assignedReviews->firstWhere('reviewer_id', $person->id);
                    $deadline = $person->pivot->deadline_at ? \Carbon\Carbon::parse($person->pivot->deadline_at) : null;
                    $state = $evaluation?->submitted_at ? 'completed' : ($deadline?->isPast() ? 'overdue' : ($evaluation ? 'in_progress' : 'not_started'));
                @endphp
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><a href="{{ route('admin.reviewers.history', $person) }}" class="text-sm font-semibold text-blue-700 hover:underline" title="View reviewer history">{{ $person->name }}</a><x-status-badge :status="$state" /></div>
                    <dl class="mt-3 space-y-1 text-xs text-slate-600">
                        <div><dt class="inline font-medium">Assigned:</dt> <dd class="inline">{{ $person->pivot->created_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</dd></div>
                        <div><dt class="inline font-medium">Deadline:</dt> <dd class="inline">{{ $deadline?->format('M j, Y g:i A') ?? 'Not set' }}</dd></div>
                        <div><dt class="inline font-medium">Last saved:</dt> <dd class="inline">{{ $evaluation?->updated_at?->format('M j, Y g:i A') ?? 'No saved evaluation' }}</dd></div>
                        @if ($evaluation?->submitted_at)<div><dt class="inline font-medium">Submitted:</dt> <dd class="inline">{{ $evaluation->submitted_at->format('M j, Y g:i A') }}</dd></div>@endif
                    </dl>
                </article>
            @empty
                <p class="text-sm text-slate-500">No reviewers assigned yet.</p>
            @endforelse
        </div>
    </div>
    <div>
        <h4 class="font-semibold text-slate-900">Evaluation timeline</h4>
        <ol class="mt-3 space-y-4 border-l-2 border-blue-200 pl-4">
            @forelse ($events->sortBy('date') as $event)
                <li class="relative text-sm"><span aria-hidden="true" class="absolute -left-[22px] top-1 h-2.5 w-2.5 rounded-full bg-blue-600"></span><p class="font-medium text-slate-800">{{ $event['label'] }}</p><time class="text-xs text-slate-500" datetime="{{ $event['date']->toIso8601String() }}">{{ $event['date']->format('M j, Y g:i A') }}</time></li>
            @empty
                <li class="text-sm text-slate-500">No recorded events yet.</li>
            @endforelse
        </ol>
    </div>
</section>
