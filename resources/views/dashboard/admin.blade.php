<x-categorization-tracking :categorization="$data['categorization']" :stages="$data['stages']" />

<section class="mt-8 grid gap-6 lg:grid-cols-[1.5fr,1fr]">
    <div class="rounded-2xl border-l-4 border-cherry-500 bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Recent Activity</h3>
            <a href="{{ route('admin.activity.index') }}" class="text-sm font-medium text-cherry-700">View all</a>
        </div>
        <div class="mt-4 grid gap-3">
            @forelse ($data['recentActivity'] as $log)
                <div class="rounded-xl bg-slate-50 p-3 text-sm">
                    <div class="flex items-center justify-between gap-2 text-xs text-slate-400">
                        <span>{{ $log->causer->name ?? 'System' }}</span>
                        <span>{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-slate-700">{{ $log->description }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No activity recorded yet.</div>
            @endforelse
        </div>
    </div>

    <div class="grid gap-6">
        <div class="rounded-2xl border-l-4 border-slate-400 bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">System &amp; User Monitoring</h3>
            <div class="mt-4 grid gap-3 text-sm">
                <a href="{{ route('admin.users.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">
                    <span>Disabled accounts</span>
                    <span class="font-semibold text-slate-900">{{ $data['disabledUsers'] }}</span>
                </a>
                <a href="{{ route('admin.activity.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">
                    <span>Full activity &amp; action log</span>
                    <span class="text-cherry-700">Open &rarr;</span>
                </a>
                <a href="{{ route('repository.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">
                    <span>Published to repository</span>
                    <span class="font-semibold text-slate-900">{{ $data['publishedResearch'] }}</span>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h3 class="text-lg font-semibold text-slate-900">Operational Oversight</h3>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Reference</th>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Researcher</th>
                    <th class="px-4 py-3 font-medium">Stage</th>
                    <th class="px-4 py-3 font-medium">Versions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($data['oversight'] as $submission)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $submission->reference_code }}</td>
                        <td class="px-4 py-3 text-slate-800">{{ $submission->title }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $submission->researcher->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$submission->status" /></td>
                        <td class="px-4 py-3">
                            @forelse ($submission->snapshots->sortByDesc('version') as $snapshot)
                                <a href="{{ route('admin.submissions.manuscript.version', [$submission, $snapshot]) }}" target="_blank" class="mr-2 inline-block text-xs font-medium text-cherry-700 hover:underline">v{{ $snapshot->version }}</a>
                            @empty
                                <span class="text-xs text-slate-400">&mdash;</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Nothing currently in progress.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
