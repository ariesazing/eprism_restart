@php
    $recommendationLabels = ['approve' => 'Approve', 'minor_revision' => 'Minor Revision', 'major_revision' => 'Major Revision'];
@endphp

<section class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Assigned Submissions</div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['assignedSubmissions']->count() }}</div>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Evaluations Submitted</div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['reviews']->count() }}</div>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Comments Authored</div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['commentsAuthored'] }}</div>
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
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->status->label() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->snapshots_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('reviewer.submissions.show', $submission) }}" class="text-sm font-medium text-red-700">Open &rarr;</a>
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
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">{{ $recommendationLabels[$review->recommendation] ?? $review->recommendation }}</span>
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
