<section class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">My Submissions</div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['submissions']->count() }}</div>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Approved &amp; Published</div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['submissions']->where('status', \App\Enums\SubmissionStatus::APPROVED)->count() }}</div>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Needing Revision</div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $data['submissions']->where('status', \App\Enums\SubmissionStatus::REVISIONS_REQUIRED)->count() }}</div>
    </div>
</section>

<section class="mt-8">
    <h3 class="text-lg font-semibold text-slate-900">Submission Tracking</h3>
    <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Reference</th>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Stage</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($data['submissions'] as $submission)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $submission->reference_code }}</td>
                        <td class="px-4 py-3 text-slate-800">{{ $submission->title }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->status->label() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('submissions.show', $submission) }}" class="text-sm font-medium text-red-700">Open &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="mt-8 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Submission Readiness Result</h3>
        <div class="mt-4 grid gap-3">
            @forelse ($data['readiness'] as $submissionId => $assessment)
                @php $submission = $data['submissions']->firstWhere('id', $submissionId); @endphp
                <div class="rounded-xl bg-slate-50 p-4 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-slate-900">{{ $submission->title }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $assessment['ready'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $assessment['ready'] ? 'Ready to submit' : 'Incomplete' }}
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">
                        Chapters: {{ $assessment['sections']['done'] }}/{{ $assessment['sections']['total'] }}
                        &middot; Attachments: {{ $assessment['attachments']['done'] }}/{{ $assessment['attachments']['total'] }}
                    </div>
                    @if (! $assessment['ready'])
                        <ul class="mt-2 list-inside list-disc text-xs text-slate-500">
                            @foreach ($assessment['sections']['missing'] as $missing)
                                <li>{{ $missing }}</li>
                            @endforeach
                            @foreach ($assessment['attachments']['missing'] as $missing)
                                <li>{{ $missing }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No drafts or returned submissions to assess.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Reviewer Feedbacks</h3>
        <div class="mt-4 grid gap-3">
            @forelse ($data['feedback'] as $comment)
                <div class="rounded-xl bg-slate-50 p-4 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-slate-900">{{ $comment->author->name ?? 'Reviewer' }}</span>
                        <span class="text-xs text-slate-400">{{ $comment->submission->reference_code }}</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">{{ $comment->submission->title }} &middot; p.{{ $comment->page_number }}</div>
                    <p class="mt-2 text-slate-700">{{ $comment->body }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No reviewer feedback yet.</div>
            @endforelse
        </div>
    </div>
</section>
