@php
    $activityColor = fn (string $action) => match (true) {
        str_starts_with($action, 'submission.approved'), str_starts_with($action, 'submission.promoted') => 'bg-emerald-500',
        str_starts_with($action, 'submission.revisions_required') => 'bg-amber-500',
        str_starts_with($action, 'submission.submitted'), str_starts_with($action, 'submission.resubmitted') => 'bg-blue-500',
        str_starts_with($action, 'comment.') => 'bg-indigo-500',
        default => 'bg-slate-400',
    };
@endphp

<section class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h3 class="text-lg font-semibold text-slate-900">Recent Activity</h3>
    <div class="mt-4 grid gap-3">
        @forelse ($data['recentActivity'] as $activity)
            <div class="flex items-start gap-3 text-sm">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $activityColor($activity->action) }}"></span>
                <div class="flex-1">
                    <p class="text-slate-700">{{ $activity->description }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $activity->created_at->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No recent activity yet.</div>
        @endforelse
    </div>
</section>
