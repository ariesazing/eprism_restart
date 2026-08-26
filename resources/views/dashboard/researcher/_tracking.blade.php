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

<section class="mt-8" x-data="{ tab: 'all' }">
    <div class="flex items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-slate-900">My Submissions</h3>
        <a href="{{ route('submissions.index') }}" class="text-sm font-medium text-cherry-700 hover:text-cherry-800">View all &rarr;</a>
    </div>

    <div class="mt-4 flex gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1 text-sm">
        @foreach ($tabs as $tabItem)
            <button
                type="button"
                @click="tab = '{{ $tabItem['value'] }}'"
                :class="tab === '{{ $tabItem['value'] }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="whitespace-nowrap rounded-lg px-3 py-1.5 font-medium transition"
            >
                {{ $tabItem['label'] }}
                <span class="ml-1 text-xs text-slate-400">{{ $tabItem['count'] }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Reference</th>
                    <th class="px-4 py-3 font-medium">Research Title</th>
                    <th class="px-4 py-3 font-medium">Current Stage</th>
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
                            <a href="{{ route('submissions.show', $submission) }}" class="text-sm font-medium text-cherry-700">View &rarr;</a>
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
