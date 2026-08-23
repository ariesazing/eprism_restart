<section class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Assigned Submissions</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['assignedSubmissions']->count() }}</div>
        <p class="mt-1 text-xs text-slate-400">Submissions in your review queue</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Evaluations Submitted</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75 11.25 15 15 9.75"></path><circle cx="12" cy="12" r="9"></circle></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['reviews']->count() }}</div>
        <p class="mt-1 text-xs text-slate-400">Recommendations you've finalized</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Comments Authored</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['commentsAuthored'] }}</div>
        <p class="mt-1 text-xs text-slate-400">Inline manuscript comments left</p>
    </div>
</section>

<section class="mt-8">
    <h3 class="text-lg font-semibold text-slate-900">Assignment Tracking</h3>
    <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Reference</th>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Researcher</th>
                    <th class="px-4 py-3 font-medium">Stage</th>
                    <th class="px-4 py-3 font-medium">Versions</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($data['assignedSubmissions'] as $submission)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $submission->reference_code }}</td>
                        <td class="px-4 py-3 text-slate-800">{{ $submission->title }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->researcher->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$submission->status" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->snapshots_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('reviewer.submissions.show', $submission) }}" class="text-sm font-medium text-cherry-700">Open &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No assigned submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="mt-8">
    <h3 class="text-lg font-semibold text-slate-900">Rubric &amp; Feedback Metrics</h3>
    <div class="mt-4 grid gap-3">
        @forelse ($data['reviews'] as $review)
            <div class="rounded-xl bg-slate-50 p-4 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-slate-900">{{ $review->submission->title }}</span>
                    <x-recommendation-badge :recommendation="$review->recommendation" />
                </div>
                <div class="mt-2 grid grid-cols-4 gap-2 text-xs text-slate-600">
                    <div>Originality: {{ $review->criteria_scores['originality'] ?? '—' }}</div>
                    <div>Methodology: {{ $review->criteria_scores['methodology'] ?? '—' }}</div>
                    <div>Clarity: {{ $review->criteria_scores['clarity'] ?? '—' }}</div>
                    <div>Compliance: {{ $review->criteria_scores['compliance'] ?? '—' }}</div>
                </div>
                <p class="mt-2 text-slate-700">{{ $review->comments }}</p>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No evaluations submitted yet.</div>
        @endforelse
    </div>
</section>
