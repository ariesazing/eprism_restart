@php
    $tabs = [
        ['value' => 'all', 'label' => 'All', 'count' => $data['submissions']->count()],
        ...collect(\App\Enums\SubmissionStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => $data['statusCounts'][$status->value] ?? 0,
        ])->all(),
    ];
@endphp

<section class="mt-8" data-live-region="researcher-tracking" x-data="{ tab: 'all' }">
    <div class="flex items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-slate-900">My Submissions</h3>
        <a href="{{ route('submissions.index') }}" class="text-sm font-medium text-cherry-700 hover:text-cherry-800">View all &rarr;</a>
    </div>

    <div class="app-card mt-4 overflow-x-auto bg-white">
        <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Reference</th>
                    <th class="px-4 py-3 font-medium">Research Title</th>
                    <th class="px-4 py-3 font-medium">
                        Current Stage
                        <button type="button" class="table-header-control" popovertarget="tracking-stage-filter" aria-label="Filter current stage" title="Filter current stage" :class="tab !== 'all' ? 'bg-blue-100' : ''"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="2" d="m6 9 6 6 6-6"/></svg></button>
                        <div id="tracking-stage-filter" popover="auto" class="table-filter-popover" @beforetoggle="if ($event.newState === 'open') { const r = $el.previousElementSibling.getBoundingClientRect(); $el.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 280)) + 'px'; $el.style.top = Math.max(8, Math.min(r.bottom + 6, window.innerHeight - 280)) + 'px'; }">
                            @foreach ($tabs as $tabItem)
                                <button type="button" @click="tab = '{{ $tabItem['value'] }}'; $el.parentElement.hidePopover()" :class="tab === '{{ $tabItem['value'] }}' ? 'bg-blue-50 font-bold' : ''" class="block w-full rounded-lg p-2 text-left">{{ $tabItem['label'] }} ({{ $tabItem['count'] }})</button>
                            @endforeach
                        </div>
                    </th>
                    <th class="px-4 py-3 font-medium">Last Updated</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($data['submissions'] as $submission)
                    <tr x-show="tab === 'all' || tab === '{{ $submission->status->value }}'">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $submission->reference_code }}</td>
                        <td class="max-w-xs truncate px-4 py-3 text-slate-800">{{ $submission->title }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$submission->status" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $submission->updated_at->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('submissions.show', $submission) }}" class="text-sm font-medium text-cherry-700" title="View submission" aria-label="View submission"><x-action-icon action="View" /><span class="sr-only">View submission</span></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                            <p class="font-medium text-slate-600">No submissions yet</p>
                            <p class="mt-1 text-sm text-slate-400">You haven't created a research submission.</p>
                            <a href="{{ route('submissions.create') }}" class="mt-3 inline-flex rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">+ Create New Submission</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
