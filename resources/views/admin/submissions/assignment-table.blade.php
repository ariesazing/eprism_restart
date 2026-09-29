<div class="assignment-table-scroll app-card bg-white" data-live-region="admin-submissions"
     role="region" aria-label="Reviewer assignments, scroll to see all columns" tabindex="0"
     @scroll="$el.querySelectorAll('[popover]:popover-open').forEach(panel => panel.hidePopover())">
    <table class="assignment-table research-table text-sm" data-table-custom-cells>
        <caption class="sr-only">Research submissions and assigned reviewers. View a submission for complete details.</caption>
        <colgroup>
            <col class="assignment-col-research">
            <col class="assignment-col-type">
            <col class="assignment-col-status">
            <col class="assignment-col-date">
            <col class="assignment-col-reviewers">
            <col class="assignment-col-actions">
        </colgroup>
        <thead>
            <tr>
                <th scope="col" class="assignment-research-cell">Research</th>
                <th scope="col">Type</th>
                <th scope="col">Status</th>
                <th scope="col" class="assignment-date-cell" aria-sort="{{ $filters['sort'] === 'asc' ? 'ascending' : 'descending' }}">
                    <a href="{{ request()->fullUrlWithQuery(['sort' => $filters['sort'] === 'asc' ? 'desc' : 'asc', 'page' => null]) }}" class="assignment-date-sort" title="Sort by submission date">
                        Submitted At
                        <svg aria-hidden="true" class="h-3.5 w-3.5 text-blue-600 {{ $filters['sort'] === 'asc' ? 'rotate-180' : '' }}" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M6 13l6 6 6-6" /></svg>
                    </a>
                </th>
                <th scope="col">Reviewers</th>
                <th scope="col" class="assignment-actions-cell">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($submissions as $submission)
                <tr>
                    <td class="assignment-research-cell" data-sort-value="{{ $submission->title }}">
                        <div class="assignment-research-summary">
                            <span class="assignment-reference" title="{{ $submission->reference_code }}">{{ $submission->reference_code ?? 'No reference code' }}</span>
                            <span class="assignment-research-title" title="{{ $submission->title }}">{{ $submission->title }}</span>
                            <span class="assignment-researcher" title="{{ $submission->researcher?->name ?? 'Unknown researcher' }}">{{ $submission->researcher?->name ?? 'Unknown researcher' }}</span>
                        </div>
                    </td>
                    <td class="assignment-metadata">{{ ucfirst($submission->research_type) }} &middot; {{ ucfirst($submission->classification) }}</td>
                    <td><x-status-badge :status="$submission->status" /></td>
                    <td class="assignment-date-cell assignment-metadata">
                        @if ($submission->submitted_at)
                            <time datetime="{{ $submission->submitted_at->toIso8601String() }}" title="{{ $submission->submitted_at->format('M j, Y g:i A') }}">{{ $submission->submitted_at->format('M j, Y g:i A') }}</time>
                        @else
                            &mdash;
                        @endif
                    </td>
                    <td class="assignment-reviewers-cell">
                        @php
                            $assignedReviewers = $submission->reviewers->values();
                            $remainingReviewers = max(0, $assignedReviewers->count() - 2);
                            $reviewerListId = 'assignment-reviewers-'.$submission->id;
                        @endphp
                        <div class="assignment-reviewer-summary" x-data="{ expanded: false }">
                            @forelse ($assignedReviewers->take(2) as $person)
                                <div class="assignment-reviewer-line">
                                    <a class="assignment-reviewer-name hover:underline" href="{{ route('admin.reviewers.history', $person) }}" title="View history for {{ $person->name }}">{{ $person->name }}</a>
                                    @if ($loop->last && $remainingReviewers)
                                        <button type="button" class="assignment-more-reviewers" popovertarget="{{ $reviewerListId }}" aria-controls="{{ $reviewerListId }}" :aria-expanded="expanded.toString()" aria-label="Show all {{ $assignedReviewers->count() }} reviewers for {{ $submission->reference_code ?? $submission->title }}">+{{ $remainingReviewers }} more</button>
                                    @endif
                                </div>
                            @empty
                                <span class="text-slate-500">Unassigned</span>
                            @endforelse
                            @if ($remainingReviewers)
                                <div id="{{ $reviewerListId }}" popover="auto" class="assignment-reviewer-popover" role="region" aria-label="All assigned reviewers"
                                     @beforetoggle="expanded = $event.newState === 'open'; if (expanded) { const r = $el.parentElement.querySelector('[popovertarget]').getBoundingClientRect(); $el.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 328)) + 'px'; $el.style.top = Math.max(8, Math.min(r.bottom + 6, window.innerHeight - 280)) + 'px'; }">
                                    <h4 class="font-semibold text-slate-900">Assigned reviewers ({{ $assignedReviewers->count() }})</h4>
                                    <ul class="mt-3 space-y-2">
                                        @foreach ($assignedReviewers as $person)
                                            <li><a href="{{ route('admin.reviewers.history', $person) }}" class="text-blue-700 hover:underline">{{ $person->name }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="assignment-actions-cell">
                        <div class="assignment-actions">
                            <button type="button" title="View research and evaluation progress" aria-label="View research and evaluation progress" @click="$dispatch('open-modal', 'submission-{{ $submission->id }}-details'); window.initSubmissionDiscussion?.(document.getElementById('discussion-{{ $submission->id }}'))" class="assignment-action">
                                <svg aria-hidden="true" class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button type="button" title="Assign reviewers" aria-label="Assign reviewers" @click="$dispatch('open-modal', 'submission-{{ $submission->id }}-assign')" class="assignment-action">
                                <svg aria-hidden="true" class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="assignment-empty">No submissions match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
