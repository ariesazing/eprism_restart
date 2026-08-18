<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            @if ($scope === 'own')
                My Approved Research
            @elseif ($scope === 'reviewed')
                Research I Reviewed
            @else
                Research Repository
            @endif
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($scope === 'own')
                <p class="mb-6 text-sm text-slate-500">Showing only your own approved research.</p>
            @elseif ($scope === 'reviewed')
                <p class="mb-6 text-sm text-slate-500">Showing only the research you reviewed that has been approved.</p>
            @endif

            <form method="GET" action="{{ route('repository.index') }}" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title or researcher" class="w-56 flex-1 rounded-xl border-slate-300 text-sm" />
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
                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
                    @if ($filters['search'] || $filters['research_type'] || $filters['classification'])
                        <a href="{{ route('repository.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </form>

            <div class="grid gap-4 lg:grid-cols-2">
                @forelse ($submissions as $submission)
                    <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                            <div class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Approved</div>
                        </div>
                        <div class="mt-1 font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                        <p class="mt-2 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }}</p>
                        <div class="mt-4 text-sm text-slate-500">Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'Not assigned' }}</div>
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
                        @if ($scope === 'own')
                            You have no approved research yet.
                        @elseif ($scope === 'reviewed')
                            You have not reviewed any approved research yet.
                        @else
                            No approved research yet.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>