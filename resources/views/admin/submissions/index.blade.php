<x-app-layout skeleton="table">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Reviewer Assignment</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            <x-filter-bar
                :action="route('admin.submissions.index')"
                :has-active-filters="(bool) ($filters['search'] || $filters['status'] || $filters['research_type'] || $filters['classification'] || $filters['reviewer'])"
                :clear-url="route('admin.submissions.index')"
            >
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search submissions" class="w-44 flex-1 rounded-xl border-slate-300 text-sm" />
                <select name="status" class="w-36 rounded-xl border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\SubmissionStatus::cases() as $status)
                        @if ($status !== \App\Enums\SubmissionStatus::DRAFT)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endif
                    @endforeach
                </select>
                <select name="research_type" class="w-36 rounded-xl border-slate-300 text-sm">
                    <option value="">All types</option>
                    <option value="basic" @selected($filters['research_type'] === 'basic')>Basic Research</option>
                    <option value="action" @selected($filters['research_type'] === 'action')>Action Research</option>
                </select>
                <select name="classification" class="w-36 rounded-xl border-slate-300 text-sm">
                    <option value="">All classes</option>
                    <option value="proposal" @selected($filters['classification'] === 'proposal')>Proposal</option>
                    <option value="completed" @selected($filters['classification'] === 'completed')>Completed Research</option>
                </select>
                <select name="reviewer" class="w-36 rounded-xl border-slate-300 text-sm">
                    <option value="">All reviewers</option>
                    <option value="unassigned" @selected($filters['reviewer'] === 'unassigned')>Unassigned</option>
                    @foreach ($reviewers as $reviewer)
                        <option value="{{ $reviewer->id }}" @selected($filters['reviewer'] == $reviewer->id)>{{ $reviewer->name }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Reference</th>
                            <th class="px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Researcher</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Reviewers</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($submissions as $submission)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $submission->reference_code }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ $submission->title }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->researcher->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ ucfirst($submission->research_type) }} &middot; {{ ucfirst($submission->classification) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->status->label() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $submission->reviewers->pluck('name')->join(', ') ?: 'Unassigned' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button type="button" @click="$dispatch('open-modal', 'submission-{{ $submission->id }}-details')" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No submissions match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @foreach ($submissions as $submission)
                <x-modal name="submission-{{ $submission->id }}-details" max-width="2xl">
                    <div class="max-h-[85vh] overflow-y-auto p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }} · {{ $submission->status->label() }}</p>
                                @if ($submission->latestSnapshot())
                                    <a href="{{ route('admin.submissions.manuscript.review', $submission) }}" class="mt-2 inline-block text-sm font-medium text-cherry-700 hover:underline">Open Manuscript &amp; Comments</a>
                                @endif
                                @php
                                    $reviewSummary = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_REVIEW_SUMMARY);
                                    $routingSlip = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_ROUTING_SLIP);
                                @endphp
                                @if ($reviewSummary || $routingSlip)
                                    <p class="mt-2 flex flex-wrap gap-3 text-sm">
                                        @if ($reviewSummary)
                                            <a href="{{ route('rapm-documents.show', $reviewSummary) }}" target="_blank" class="font-medium text-cherry-700 hover:underline">Review Summary</a>
                                        @endif
                                        @if ($routingSlip)
                                            <a href="{{ route('rapm-documents.show', $routingSlip) }}" target="_blank" class="font-medium text-cherry-700 hover:underline">Routing Slip</a>
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <button type="button" @click="$dispatch('close-modal', 'submission-{{ $submission->id }}-details')" class="rounded-md p-1 text-slate-400 hover:bg-slate-100">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
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
                                <button type="submit" class="mt-3 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save Reviewers</button>
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
                    </div>
                </x-modal>
            @endforeach
        </div>
    </div>
</x-app-layout>
