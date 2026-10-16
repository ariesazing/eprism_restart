@props(['submissions', 'badgeLabel', 'stage', 'emptyMessage', 'filters'])

@if ($submissions->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
        {{ $emptyMessage }}
    </div>
@else
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="research-table min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3">Research</th>
                    <th scope="col" class="px-5 py-3">Researcher</th>
                    <th scope="col" class="px-5 py-3">Type</th>
                    <th scope="col" class="px-5 py-3">Authors</th>
                    <th scope="col" class="px-5 py-3">Approved</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($submissions as $submission)
                    @php
                        $approvedAt = $stage === 'proposal' ? $submission->proposal_approved_at : $submission->approved_at;
                        $authors = $submission->authorship();
                    @endphp
                    <tr class="align-top hover:bg-slate-50/70">
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $submission->title }}</div>
                            <div class="mt-1 font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                        </td>
                        <td class="px-5 py-4 text-slate-700">{{ $submission->researcher?->name ?? 'Unknown researcher' }}</td>
                        <td class="px-5 py-4 whitespace-nowrap text-slate-700">{{ ucfirst($submission->research_type) }} Research</td>
                        <td class="px-5 py-4 whitespace-nowrap text-slate-700">{{ $authors === 'collaborative' ? 'Collaborative' : 'Sole' }}</td>
                        <td class="px-5 py-4 whitespace-nowrap text-slate-600">{{ $approvedAt?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-5 py-4"><span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $badgeLabel }}</span></td>
                        <td class="px-5 py-4 text-right">
                            <x-repository.submission-card :submission="$submission" :badge-label="$badgeLabel" :stage="$stage" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
