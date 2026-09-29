<x-app-layout skeleton="table">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Reviewer Assignment</h2>
    </x-slot>

    @vite(['resources/js/submission-discussion.js'])

    <div class="py-10">
        <div class="reviewer-assignment-page mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())<ul role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <x-filter-bar
                :action="route('admin.submissions.index')"
                :has-active-filters="(bool) ($filters['search'] || $filters['status'] || $filters['research_type'] || $filters['classification'] || $filters['reviewer'] || $filters['sort'] !== 'desc')"
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
                <div>
                    <label class="text-xs font-medium text-slate-700">Sort</label>
                    <select name="sort" class="mt-1 w-40 rounded-xl border-slate-300 text-sm">
                        <option value="desc" @selected($filters['sort'] === 'desc')>Submitted: newest first</option>
                        <option value="asc" @selected($filters['sort'] === 'asc')>Submitted: oldest first</option>
                    </select>
                </div>
            </x-filter-bar>

            @include('admin.submissions.assignment-table')

            <div>
                {{ $submissions->links() }}
            </div>

            @foreach ($submissions as $submission)
                <x-modal name="submission-{{ $submission->id }}-details" max-width="3xl">
                    <div class="max-h-[85vh] overflow-y-auto p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $submission->researcher?->name ?? 'Unknown researcher' }} · {{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }} · {{ $submission->status->label() }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    @if ($submission->latestSnapshot())
                                        <a href="{{ route('admin.submissions.manuscript.review', $submission) }}" class="text-sm font-medium text-cherry-700 hover:underline"><x-action-icon action="Open" />Open Manuscript &amp; Comments</a>
                                        @if ($submission->snapshots->count() > 1)
                                            <div class="relative" x-data="{ open: false }">
                                                <button type="button" @click="open = ! open" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Other Versions" />Other Versions</button>
                                                <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-10 mt-2 grid gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                                                    @foreach ($submission->snapshots as $snapshot)
                                                        @if ($snapshot->files_pruned_at)
                                                            <span class="text-xs text-slate-500">v{{ $snapshot->version }} - Files removed</span>
                                                        @else
                                                        <a href="{{ route('admin.submissions.manuscript.version.review', [$submission, $snapshot]) }}" class="flex items-center justify-between gap-4 rounded-lg px-3 py-2 text-sm {{ $loop->first ? 'bg-cherry-50 text-cherry-700' : 'text-slate-700 hover:bg-slate-50' }}">
                                                            <span>Version {{ $snapshot->version }}</span>
                                                            @if ($loop->first)
                                                                <span class="text-xs font-medium">Current</span>
                                                            @endif
                                                        </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endif

                                    @include('submissions.partials.discussion', [
                                        'submission' => $submission,
                                        'discussionUrl' => route('admin.submissions.discussion.index', $submission),
                                    ])


                                </div>
                                @php
                                    $reviewSummary = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_REVIEW_SUMMARY);
                                    $routingSlip = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_ROUTING_SLIP);
                                @endphp
                                @if ($reviewSummary || $routingSlip)
                                    <p class="mt-2 flex flex-wrap gap-3 text-sm">
                                        @if ($reviewSummary)
                                            <a href="{{ route('rapm-documents.show', $reviewSummary) }}" target="_blank" class="font-medium text-cherry-700 hover:underline"><x-action-icon action="Review Summary" />Review Summary</a>
                                        @endif
                                        @if ($routingSlip)
                                            <a href="{{ route('rapm-documents.show', $routingSlip) }}" target="_blank" class="font-medium text-cherry-700 hover:underline"><x-action-icon action="Routing Slip" />Routing Slip</a>
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <button type="button" @click="$dispatch('close-modal', 'submission-{{ $submission->id }}-details')" class="rounded-md p-1 text-slate-400 hover:bg-slate-100">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        @include('admin.submissions.monitoring')

                        @if ($submission->reviews->whereNotNull('submitted_at')->isNotEmpty())
                            <div class="mt-6 app-card-inset p-4">
                                <h4 class="font-semibold text-slate-900">Reviewer Evaluations</h4>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    @foreach ($submission->reviews->whereNotNull('submitted_at') as $review)
                                        <div class="app-card-inset p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="font-medium text-slate-900">{{ $review->reviewer->name }}</div>
                                                <div class="text-xs text-slate-500">{{ str($review->recommendation)->replace('_', ' ')->headline() }}</div>
                                            </div>
                                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600 sm:grid-cols-3">
                                                {{-- Top-level items only (a parent's score is already its children's sum —
                                                     see Review::breakdown()) — the two completed-research rubrics run to
                                                     15-19 line items total, too many to usefully show every sub-item here. --}}
                                                @foreach ($review->breakdown() as $section)
                                                    @foreach ($section['items'] as $item)
                                                        <div>{{ $item['code'] }}. {{ $item['label'] }}: {{ $item['score'] }}/{{ $item['max'] }}</div>
                                                    @endforeach
                                                @endforeach
                                                <div class="font-semibold text-slate-800">Total: {{ $review->totalScore() }}/{{ \App\Evaluation\ResearchEvaluationRubric::MAX_SCORE }}</div>
                                            </div>
                                            <p class="mt-3 text-sm text-slate-700">{{ $review->comments }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($submission->documents->isNotEmpty())
                            <div class="mt-6 app-card-inset p-4">
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

                <x-modal name="submission-{{ $submission->id }}-assign" max-width="lg">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                            </div>
                            <button type="button" @click="$dispatch('close-modal', 'submission-{{ $submission->id }}-assign')" class="rounded-md p-1 text-slate-400 hover:bg-slate-100">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.submissions.assign-reviewer', $submission) }}" class="app-card-inset mt-5 p-4" x-data="reviewerAssignment(@js($reviewers->map(fn ($person) => ['id' => $person->id, 'assigned_submissions_count' => $person->assigned_submissions_count])), @js($submission->reviewers->pluck('id')->all()))">
                            @csrf
                            @method('PATCH')
                            <h4 class="font-semibold text-slate-900">Assign Reviewers</h4>
                            <p class="mt-1 text-xs text-slate-500">Select at least 1 reviewer. Revisions, promotion to completed, and final approval are all decided automatically from their recommendations &mdash; admins only assign who reviews.</p>
                            <div class="mt-3">
                                <x-dropdown align="left" width="w-72 max-w-full" :inline="true">
                                    <x-slot name="trigger">
                                        <button type="button" class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 sm:w-72">
                                            <span x-text="selected.length + (selected.length === 1 ? ' reviewer selected' : ' reviewers selected')"></span>
                                            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <div @click.stop class="max-h-64 overflow-y-auto overscroll-contain p-2">
                                            @forelse ($reviewers as $reviewer)
                                                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                                    <input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}" @checked($submission->reviewers->contains('id', $reviewer->id)) x-model="selected" class="rounded border-slate-300" />
                                                    {{ $reviewer->name }} <span class="ml-auto whitespace-nowrap text-xs font-semibold text-blue-700">{{ $reviewer->assigned_submissions_count }} active</span>
                                                </label>
                                            @empty
                                                <p class="px-2 py-1.5 text-sm text-slate-400">No active reviewers available.</p>
                                            @endforelse
                                        </div>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                            <div x-show="projection.warning || projection.blocked" x-cloak role="alert" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                <p x-text="projection.blocked ? 'A reviewer would have at least two assignments above average. Choose another reviewer to save.' : 'A reviewer would have more than one assignment above average. Enter a short reason to continue.'"></p>
                                <label x-show="projection.warning" class="mt-2 block">Reason for this assignment<textarea name="load_override_reason" :required="projection.warning" :disabled="!projection.warning" minlength="10" maxlength="1000" class="mt-1 w-full rounded-lg border-amber-300" rows="2">{{ old('load_override_reason') }}</textarea></label>
                            </div>
                            <label class="mt-4 block text-sm font-medium text-slate-700">Evaluation deadline
                                <input type="datetime-local" name="deadline_at" value="{{ $submission->reviewer_deadline_at?->format('Y-m-d\TH:i') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            </label>
                            <button type="submit" :disabled="projection.blocked || !selected.length" class="disabled:opacity-50 mt-3 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800"><x-action-icon action="Save Reviewers" />Save Reviewers</button>
                        </form>
                    </div>
                </x-modal>
            @endforeach
        </div>
    </div>

    {{-- One grammar-review modal for the whole list: each submission's "Check grammar" button hands it that submission's own chapters. --}}
    @include('researcher.submissions.partials.grammar-review-modal', ['chapters' => [], 'viewer' => 'admin'])
</x-app-layout>
