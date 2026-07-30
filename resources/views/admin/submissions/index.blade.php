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
                            <p class="mt-1 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }} · {{ $submission->status->label() }}</p>
                            @if ($submission->latestSnapshot())
                                <a href="{{ route('admin.submissions.manuscript.review', $submission) }}" class="mt-2 inline-block text-sm font-medium text-cyan-700 hover:underline">Open Manuscript &amp; Comments</a>
                            @endif
                        </div>
                        <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">
                            Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'Unassigned' }}
                        </div>
                    </div>

                    <div class="mt-5">
                        <form method="POST" action="{{ route('admin.submissions.assign-reviewer', $submission) }}" class="rounded-2xl border border-slate-200 p-4">
                            @csrf
                            @method('PATCH')
                            <h4 class="font-semibold text-slate-900">Assign Reviewers</h4>
                            <p class="mt-1 text-xs text-slate-500">Select at least 3 reviewers. Revisions, promotion to completed, and final approval are all decided automatically from their recommendations &mdash; admins only assign who reviews.</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($reviewers as $reviewer)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}" @checked($submission->reviewers->contains('id', $reviewer->id)) class="rounded border-slate-300" />
                                        {{ $reviewer->name }}
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="mt-3 rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white">Save Reviewers</button>
                        </form>
                    </div>

                    @if ($submission->reviews->isNotEmpty())
                        <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                            <h4 class="font-semibold text-slate-900">Reviewer Evaluations</h4>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                @foreach ($submission->reviews as $review)
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="font-medium text-slate-900">{{ $review->reviewer->name }}</div>
                                            <div class="text-xs text-slate-500">{{ str($review->recommendation)->replace('_', ' ')->headline() }}</div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                                            @foreach (['originality' => 'Originality', 'methodology' => 'Methodology', 'clarity' => 'Clarity', 'compliance' => 'Compliance'] as $field => $label)
                                                <div>{{ $label }}: {{ $review->criteria_scores[$field] ?? '—' }}</div>
                                            @endforeach
                                        </div>
                                        <p class="mt-3 text-sm text-slate-700">{{ $review->comments }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($submission->documents->isNotEmpty())
                        <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                            <h4 class="font-semibold text-slate-900">Attachments</h4>
                            <div class="mt-3 grid gap-3 text-sm">
                                @foreach ($submission->documents as $document)
                                    <div class="flex items-center justify-between gap-3 rounded-full border border-slate-200 px-4 py-2">
                                        <a href="{{ route('admin.submissions.attachments.download', [$submission, $document]) }}" class="text-slate-700 hover:underline">{{ $document->document_type }} · {{ $document->original_name }}</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
