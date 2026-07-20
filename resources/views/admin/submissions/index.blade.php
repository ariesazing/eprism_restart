<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Workflow Administration</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            @foreach ($submissions as $submission)
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ $submission->course }} · {{ $submission->status->label() }}</p>
                            <p class="mt-3 max-w-3xl text-sm text-slate-600">{{ $submission->abstract }}</p>
                        </div>
                        <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">
                            Reviewer: {{ $submission->reviewer->name ?? 'Unassigned' }}
                        </div>
                    </div>

                    <div class="mt-5 grid gap-6 xl:grid-cols-3">
                        <form method="POST" action="{{ route('admin.submissions.assign-reviewer', $submission) }}" class="rounded-2xl border border-slate-200 p-4">
                            @csrf
                            @method('PATCH')
                            <h4 class="font-semibold text-slate-900">Assign Reviewer</h4>
                            <select name="reviewer_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm">
                                @foreach ($reviewers as $reviewer)
                                    <option value="{{ $reviewer->id }}" @selected($submission->assigned_reviewer_id === $reviewer->id)>{{ $reviewer->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="mt-3 rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white">Assign</button>
                        </form>

                        <form method="POST" action="{{ route('admin.submissions.request-revision', $submission) }}" class="rounded-2xl border border-slate-200 p-4">
                            @csrf
                            @method('PATCH')
                            <h4 class="font-semibold text-slate-900">Return for Revision</h4>
                            <textarea name="admin_notes" rows="4" placeholder="Explain the required changes" class="mt-3 w-full rounded-xl border-slate-300 text-sm"></textarea>
                            <button type="submit" class="mt-3 rounded-xl bg-amber-500 px-4 py-2 text-sm font-medium text-white">Return</button>
                        </form>

                        <form method="POST" action="{{ route('admin.submissions.approve', $submission) }}" class="rounded-2xl border border-slate-200 p-4">
                            @csrf
                            @method('PATCH')
                            <h4 class="font-semibold text-slate-900">Approve Research</h4>
                            <textarea name="admin_notes" rows="4" placeholder="Optional publication note" class="mt-3 w-full rounded-xl border-slate-300 text-sm"></textarea>
                            <button type="submit" class="mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Approve</button>
                        </form>
                    </div>

                    @if ($submission->reviews->isNotEmpty())
                        <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                            <h4 class="font-semibold text-slate-900">Reviewer Evaluations</h4>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                @foreach ($submission->reviews as $review)
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <div class="font-medium text-slate-900">{{ $review->reviewer->name }}</div>
                                                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ str($review->recommendation)->replace('_', ' ')->headline() }}</div>
                                            </div>
                                            <div class="text-xs text-slate-500">{{ $review->approved_at ? 'Approved' : 'Pending approval' }}</div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-600">
                                            @foreach ($review->criteria_scores as $criterion => $score)
                                                <div class="rounded-xl bg-white px-3 py-2">{{ str($criterion)->headline() }}: {{ $score }}/5</div>
                                            @endforeach
                                        </div>
                                        <p class="mt-3 text-sm text-slate-600">{{ $review->comments }}</p>
                                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="mt-4 grid gap-3">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="approval_notes" value="{{ $review->approval_notes }}" placeholder="Approval note" class="rounded-xl border-slate-300 text-sm" />
                                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Approve Evaluation</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($submission->documents->isNotEmpty())
                        <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                            <h4 class="font-semibold text-slate-900">Documents</h4>
                            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                                @foreach ($submission->documents as $document)
                                    <a href="{{ route('admin.submissions.documents.download', [$submission, $document]) }}" class="rounded-full border border-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-50">{{ $document->document_type }} · {{ $document->original_name }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>