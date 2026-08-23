<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between gap-2">
        <h3 class="text-lg font-semibold text-slate-900">Reviewer Feedback</h3>
        @if ($data['feedback']->isNotEmpty())
            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">{{ $data['feedback']->count() }} recent comment{{ $data['feedback']->count() === 1 ? '' : 's' }}</span>
        @endif
    </div>
    <div class="mt-4 grid gap-3">
        @forelse ($data['feedback'] as $comment)
            <div class="rounded-xl bg-slate-50 p-4 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-slate-900">{{ $comment->author->name ?? 'Reviewer' }}</span>
                    <span class="text-xs text-slate-400">{{ $comment->submission->reference_code }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ $comment->submission->title }} &middot; p.{{ $comment->page_number }}</div>
                <p class="mt-2 text-slate-700">{{ $comment->body }}</p>
                <a href="{{ route('submissions.show', $comment->submission) }}" class="mt-2 inline-flex text-xs font-medium text-cherry-700 hover:text-cherry-800">View Submission &rarr;</a>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-600">No reviewer feedback</p>
                <p class="mt-1 text-xs text-slate-400">You currently have no reviewer comments requiring action.</p>
            </div>
        @endforelse
    </div>
</div>
