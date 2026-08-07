<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Reports</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3">
                @foreach ($submissionsByStatus as $status => $total)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div class="text-sm text-slate-500">{{ str($status)->replace('_', ' ')->headline() }}</div>
                        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $total }}</div>
                    </div>
                @endforeach
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Reviewer Load</h3>
                    <form method="GET" action="{{ route('admin.reports') }}" class="mt-4 flex flex-wrap gap-2">
                        @foreach (['search', 'research_type', 'classification'] as $preserve)
                            @if ($filters[$preserve])
                                <input type="hidden" name="{{ $preserve }}" value="{{ $filters[$preserve] }}" />
                            @endif
                        @endforeach
                        <input type="text" name="reviewer_search" value="{{ $filters['reviewer_search'] }}" placeholder="Search reviewer name" class="rounded-xl border-slate-300 text-sm" />
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
                        @if ($filters['reviewer_search'])
                            <a href="{{ route('admin.reports', array_filter(['search' => $filters['search'], 'research_type' => $filters['research_type'], 'classification' => $filters['classification']])) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                        @endif
                    </form>
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
                    <form method="GET" action="{{ route('admin.reports') }}" class="mt-4 grid gap-2 sm:grid-cols-3">
                        @if ($filters['reviewer_search'])
                            <input type="hidden" name="reviewer_search" value="{{ $filters['reviewer_search'] }}" />
                        @endif
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title or researcher" class="rounded-xl border-slate-300 text-sm" />
                        <select name="research_type" class="rounded-xl border-slate-300 text-sm">
                            <option value="">All research types</option>
                            <option value="basic" @selected($filters['research_type'] === 'basic')>Basic Research</option>
                            <option value="action" @selected($filters['research_type'] === 'action')>Action Research</option>
                        </select>
                        <select name="classification" class="rounded-xl border-slate-300 text-sm">
                            <option value="">All classifications</option>
                            <option value="proposal" @selected($filters['classification'] === 'proposal')>Proposal</option>
                            <option value="completed" @selected($filters['classification'] === 'completed')>Completed Research</option>
                        </select>
                        <div class="flex gap-2 sm:col-span-3">
                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
                            @if ($filters['search'] || $filters['research_type'] || $filters['classification'])
                                <a href="{{ route('admin.reports', array_filter(['reviewer_search' => $filters['reviewer_search']])) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                            @endif
                        </div>
                    </form>
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