<x-app-layout skeleton="table">
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

            <x-filter-bar
                :action="route('repository.index')"
                :has-active-filters="(bool) ($filters['search'] || $filters['research_type'])"
                :clear-url="route('repository.index')"
                class="mb-6 block"
            >
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title or researcher" class="w-56 flex-1 rounded-xl border-slate-300 text-sm" />
                <select name="research_type" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All research types</option>
                    <option value="basic" @selected($filters['research_type'] === 'basic')>Basic Research</option>
                    <option value="action" @selected($filters['research_type'] === 'action')>Action Research</option>
                </select>
            </x-filter-bar>

            <section>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Proposal Research</h3>
                <p class="mt-1 text-sm text-slate-500">Proposals that passed review and were promoted into the completed-research phase.</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @forelse ($approvedProposals as $submission)
                        <x-repository.submission-card :submission="$submission" badge-label="Proposal Approved" />
                    @empty
                        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
                            @if ($scope === 'own')
                                None of your proposals have been approved yet.
                            @elseif ($scope === 'reviewed')
                                None of the proposals you reviewed have been approved yet.
                            @else
                                No approved proposals yet.
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="mt-10">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Completed Research</h3>
                <p class="mt-1 text-sm text-slate-500">Finished research papers, fully approved and published to the repository.</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @forelse ($completedResearch as $submission)
                        <x-repository.submission-card :submission="$submission" badge-label="Approved" />
                    @empty
                        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
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
            </section>
        </div>
    </div>
</x-app-layout>
