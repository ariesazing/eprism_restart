<x-app-layout skeleton="dashboard">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Reports</h2>
            <p class="mt-1 text-sm text-slate-500">Analytics and statistics across every research submission on file.</p>
        </div>
    </x-slot>

    @php
        $totalApproved = (int) ($submissionsByStatus['approved'] ?? 0);
        $totalEvaluations = (int) collect($recommendationCounts)->sum();

        $categorizationLabels = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];
        // The one two-series chart in this page deliberately pairs the brand's own cherry
        // red with its gold accent, rather than an arbitrary third hue — everywhere else on
        // this page uses functional/status colors (pipeline stage, recommendation outcome),
        // which stay as-is since substituting brand colors there would blur "this shows
        // status" into "this shows brand."
        $categorizationSeries = [
            'basic' => ['label' => 'Basic Research', 'color' => '#9f2944'],
            'action' => ['label' => 'Action Research', 'color' => '#b88732'],
        ];

        $stageSegments = [
            ['label' => 'Drafts', 'value' => $stages['drafts'], 'color' => '#2a78d6'],
            ['label' => 'On Evaluation', 'value' => $stages['on_evaluation'], 'color' => '#4a3aa7'],
            ['label' => 'Approved', 'value' => $stages['approved'], 'color' => '#1baf7a'],
            ['label' => 'On Revision', 'value' => $stages['on_revision'], 'color' => '#eb6834'],
        ];

        $recommendationSegments = [
            ['label' => 'Approve', 'value' => $recommendationCounts['approve'] ?? 0, 'color' => '#10b981'],
            ['label' => 'Revision', 'value' => ($recommendationCounts['revision'] ?? 0) + ($recommendationCounts['minor_revision'] ?? 0) + ($recommendationCounts['major_revision'] ?? 0), 'color' => '#f59e0b'],
        ];

        $topOrganizationalUnits = collect($byOrganizationalUnit)->take(10)->map(fn ($row) => (object) [
            'label' => $row->organizational_unit,
            'value' => $row->total,
        ]);

        $statusLabels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'On Evaluation',
            'revisions_required' => 'Revisions Required',
            'resubmitted' => 'Resubmitted',
        ];

        $revisionCycleSegments = [
            ['label' => 'No revisions', 'value' => $revisionCycles['distribution']['0'], 'color' => '#10b981'],
            ['label' => '1 revision cycle', 'value' => $revisionCycles['distribution']['1'], 'color' => '#f59e0b'],
            ['label' => '2 revision cycles', 'value' => $revisionCycles['distribution']['2'], 'color' => '#eb6834'],
            ['label' => '3+ revision cycles', 'value' => $revisionCycles['distribution']['3+'], 'color' => '#f43f5e'],
        ];
    @endphp

    <div class="reports-page py-8">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            <div class="report-section-heading"><div><span class="report-eyebrow">Research intelligence</span><h3>Performance overview</h3></div><span class="report-period">All-time metrics</span></div>
            <section class="report-metrics grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Summary metrics">
                <div class="app-card bg-white p-5">
                    <div class="text-sm text-slate-500">Total Submissions</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($totalSubmissions) }}</div>
                    <p class="report-metric-caption">Research excluding drafts</p>
                </div>
                <div class="app-card bg-white p-5">
                    <div class="text-sm text-slate-500">Approved</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($totalApproved) }}</div>
                    <p class="report-metric-caption">Currently approved submissions</p>
                </div>
                <div class="app-card bg-white p-5">
                    <div class="text-sm text-slate-500">Evaluations Submitted</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($totalEvaluations) }}</div>
                    <p class="report-metric-caption">Recorded reviewer decisions</p>
                </div>
                <div class="app-card bg-white p-5">
                    <div class="text-sm text-slate-500">Avg. Time to Approval</div>
                    <div class="mt-2 text-3xl font-semibold text-slate-900">
                        @if ($avgTimeToApproval !== null)
                            {{ $avgTimeToApproval['days'] }} <span class="text-base font-normal text-slate-500">d</span>
                            {{ $avgTimeToApproval['hours'] }} <span class="text-base font-normal text-slate-500">hr</span>
                        @else
                            <span class="text-base font-normal text-slate-500">&mdash;</span>
                        @endif
                    </div>
                    <p class="report-metric-caption">From submission to approval</p>
                </div>
            </section>

            <section class="app-card bg-white p-6">
                <h3 class="text-lg font-semibold text-slate-900">Submission Trend</h3>
                <p class="mt-1 text-sm text-slate-500">New submissions per month, last 12 months.</p>
                <div class="mt-4">
                    <x-charts.area-trend :data="$submissionTrend" color="#9f2944" />
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Categorization Metrics</h3>
                    <p class="mt-1 text-sm text-slate-500">Research type by classification.</p>
                    <div class="mt-4">
                        <x-charts.grouped-bar :categories="$categorizationLabels" :series="$categorizationSeries" :data="$categorization" />
                    </div>
                </div>

                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Research Tracking</h3>
                    <p class="mt-1 text-sm text-slate-500">Where active submissions sit in the review pipeline.</p>
                    <div class="mt-4">
                        <x-charts.segmented-bar :segments="$stageSegments" />
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]" x-data="{ showAllUnits: false }">
                <div class="app-card bg-white p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Research by Office / School Unit</h3>
                            <p class="mt-1 text-sm text-slate-500">Top {{ min(10, count($byOrganizationalUnit)) }} by submission volume.</p>
                        </div>
                        @if (count($byOrganizationalUnit) > 0)
                            <button type="button" @click="showAllUnits = ! showAllUnits" :aria-expanded="showAllUnits" aria-controls="report-units-table" class="report-table-toggle shrink-0 text-sm font-medium text-cherry-700">
                                <x-action-icon action="View" /><span x-show="! showAllUnits">View full table</span>
                                <span x-show="showAllUnits" x-cloak>Hide table</span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-4">
                        <x-charts.bar-horizontal :data="$topOrganizationalUnits" color="#9f2944" />
                    </div>

                    <div id="report-units-table" x-show="showAllUnits" x-cloak class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Office / School Unit</th>
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
                        <p class="px-4 py-3 text-sm text-slate-600">Page 1 of 1</p>
                    </div>
                </div>

                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Reviewer Recommendations</h3>
                    <p class="mt-1 text-sm text-slate-500">Across all evaluations submitted.</p>
                    <div class="mt-4">
                        <x-charts.donut :segments="$recommendationSegments" label="Evaluations" />
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Time Spent in Each Status</h3>
                    <p class="mt-1 text-sm text-slate-500">Avg/median days a submission stays in a status before moving on. Only counts submissions that have actually moved past that status.</p>
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Avg Days</th>
                                    <th class="px-4 py-3 font-medium">Median Days</th>
                                    <th class="px-4 py-3 font-medium">Observed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($statusLabels as $statusValue => $statusLabel)
                                    @php($row = $timeInStatus[$statusValue])
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ $statusLabel }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row['avg'] ?? '—' }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row['median'] ?? '—' }}</td>
                                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="px-4 py-3 text-sm text-slate-600">Page 1 of 1</p>
                    </div>
                </div>

                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Revision Cycles</h3>
                    <p class="mt-1 text-sm text-slate-500">How many revise-and-resubmit rounds submissions go through, across every submission that has been submitted at least once.</p>
                    <div class="mt-4 text-3xl font-semibold text-slate-900">
                        @if ($revisionCycles['avg'] !== null)
                            {{ $revisionCycles['avg'] }} <span class="text-base font-normal text-slate-500">avg cycles per submission</span>
                        @else
                            <span class="text-base font-normal text-slate-500">No submissions yet.</span>
                        @endif
                    </div>
                    <div class="mt-4">
                        <x-charts.distribution-columns :segments="$revisionCycleSegments" />
                    </div>
                </div>
            </section>

            <div class="report-section-heading"><div><span class="report-eyebrow">Detailed records</span><h3>Reviewers &amp; approved research</h3><p>Use the filters below to refine these two lists.</p></div></div>
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
                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Reviewer Load</h3>
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
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
                    <div class="mt-4">
                        {{ $reviewerLoads->links() }}
                    </div>
                </div>

                <div class="app-card bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Approved Research</h3>
                    <div class="mt-4 grid gap-3">
                        @forelse ($approvedResearch as $submission)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="font-medium text-slate-900">{{ $submission->title }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $submission->researcher?->name ?? 'Unknown researcher' }} · Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'N/A' }}</div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">No approved research yet.</div>
                        @endforelse
                    </div>
                    <div class="mt-4">
                        {{ $approvedResearch->links() }}
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
