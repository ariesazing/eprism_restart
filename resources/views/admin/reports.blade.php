<x-app-layout skeleton="dashboard">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Reports</h2>
            <p class="mt-1 text-sm text-slate-500">Analytics and statistics across every research submission on file.</p>
        </div>
    </x-slot>

    @php
        $totalSubmissions = (int) collect($submissionsByStatus)->sum();
        $totalApproved = (int) ($submissionsByStatus['approved'] ?? 0);
        $totalEvaluations = (int) collect($recommendationCounts)->sum();

        $categorizationLabels = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];
        $categorizationSeries = [
            'basic' => ['label' => 'Basic Research', 'color' => '#a9233a'],
            'action' => ['label' => 'Action Research', 'color' => '#2a78d6'],
        ];

        $stageSegments = [
            ['label' => 'Submitted', 'value' => $stages['submitted'], 'color' => '#2a78d6'],
            ['label' => 'On Evaluation', 'value' => $stages['on_evaluation'], 'color' => '#4a3aa7'],
            ['label' => 'Evaluated', 'value' => $stages['evaluated'], 'color' => '#1baf7a'],
            ['label' => 'On Revision', 'value' => $stages['on_revision'], 'color' => '#eb6834'],
        ];

        $recommendationSegments = [
            ['label' => 'Approve', 'value' => $recommendationCounts['approve'] ?? 0, 'color' => '#10b981'],
            ['label' => 'Minor Revision', 'value' => $recommendationCounts['minor_revision'] ?? 0, 'color' => '#f59e0b'],
            ['label' => 'Major Revision', 'value' => $recommendationCounts['major_revision'] ?? 0, 'color' => '#f43f5e'],
        ];

        $topOrganizationalUnits = collect($byOrganizationalUnit)->take(10)->map(fn ($row) => (object) [
            'label' => $row->organizational_unit,
            'value' => $row->total,
        ]);
    @endphp

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Total Submissions</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalSubmissions }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Approved</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalApproved }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Evaluations Submitted</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalEvaluations }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Avg. Time to Approval</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">
                        @if ($avgDaysToApproval !== null)
                            {{ $avgDaysToApproval }} <span class="text-base font-normal text-slate-500">days</span>
                        @else
                            <span class="text-base font-normal text-slate-500">&mdash;</span>
                        @endif
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Submission Trend</h3>
                <p class="mt-1 text-sm text-slate-500">New submissions per month, last 12 months.</p>
                <div class="mt-4">
                    <x-charts.area-trend :data="$submissionTrend" color="#a9233a" />
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Categorization Metrics</h3>
                    <p class="mt-1 text-sm text-slate-500">Research type by classification.</p>
                    <div class="mt-4">
                        <x-charts.grouped-bar :categories="$categorizationLabels" :series="$categorizationSeries" :data="$categorization" />
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Research Tracking</h3>
                    <p class="mt-1 text-sm text-slate-500">Where active submissions sit in the review pipeline.</p>
                    <div class="mt-4">
                        <x-charts.segmented-bar :segments="$stageSegments" />
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[2fr,1fr]" x-data="{ showAllUnits: false }">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Research by Organizational Unit</h3>
                            <p class="mt-1 text-sm text-slate-500">Top {{ min(10, count($byOrganizationalUnit)) }} by submission volume.</p>
                        </div>
                        @if (count($byOrganizationalUnit) > 0)
                            <button type="button" @click="showAllUnits = ! showAllUnits" class="shrink-0 text-sm font-medium text-cherry-700 hover:underline">
                                <span x-show="! showAllUnits">View full table</span>
                                <span x-show="showAllUnits" x-cloak>Hide table</span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-4">
                        <x-charts.bar-horizontal :data="$topOrganizationalUnits" color="#a9233a" />
                    </div>

                    <div x-show="showAllUnits" x-cloak class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Organizational Unit</th>
                                    <th class="px-4 py-3 font-medium">Total</th>
                                    <th class="px-4 py-3 font-medium">Proposals</th>
                                    <th class="px-4 py-3 font-medium">Completed</th>
                                    <th class="px-4 py-3 font-medium">Approved</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($byOrganizationalUnit as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ $row->organizational_unit }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row->total }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row->proposals }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row->completed }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row->approved }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No submissions with an organizational unit on file yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Reviewer Recommendations</h3>
                    <p class="mt-1 text-sm text-slate-500">Across all evaluations submitted.</p>
                    <div class="mt-4">
                        <x-charts.segmented-bar :segments="$recommendationSegments" />
                    </div>
                </div>
            </section>

            <x-filter-bar
                :action="route('admin.reports')"
                :has-active-filters="(bool) ($filters['reviewer_search'] || $filters['search'] || $filters['research_type'] || $filters['classification'])"
                :clear-url="route('admin.reports')"
            >
                <div>
                    <label class="text-xs font-medium text-slate-700">Reviewer name</label>
                    <input type="text" name="reviewer_search" value="{{ $filters['reviewer_search'] }}" placeholder="Search reviewer name" class="mt-1 rounded-xl border-slate-300 text-sm" />
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Approved research</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title or researcher" class="mt-1 rounded-xl border-slate-300 text-sm" />
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Research type</label>
                    <select name="research_type" class="mt-1 rounded-xl border-slate-300 text-sm">
                        <option value="">All research types</option>
                        <option value="basic" @selected($filters['research_type'] === 'basic')>Basic Research</option>
                        <option value="action" @selected($filters['research_type'] === 'action')>Action Research</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Classification</label>
                    <select name="classification" class="mt-1 rounded-xl border-slate-300 text-sm">
                        <option value="">All classifications</option>
                        <option value="proposal" @selected($filters['classification'] === 'proposal')>Proposal</option>
                        <option value="completed" @selected($filters['classification'] === 'completed')>Completed Research</option>
                    </select>
                </div>
            </x-filter-bar>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Reviewer Load</h3>
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Reviewer</th>
                                    <th class="px-4 py-3 font-medium">Assigned Submissions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($reviewerLoads as $reviewer)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ $reviewer->name }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $reviewer->assigned_submissions_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Approved Research</h3>
                    <div class="mt-4 grid gap-3">
                        @forelse ($approvedResearch as $submission)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="font-medium text-slate-900">{{ $submission->title }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $submission->researcher->name }} · Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'N/A' }}</div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">No approved research yet.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
