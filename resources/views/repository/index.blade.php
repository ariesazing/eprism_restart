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
                :has-active-filters="(bool) ($filters['organizational_unit'] || $filters['unit_type'] || $filters['search'] || $filters['research_type'] || !empty($filters['authorship']) || $filters['sort'] !== 'newest')"
                :clear-url="route('repository.index')"
                class="mb-6 block"
            >
                <x-table-search :value="$filters['search']" placeholder="Search title or researcher" class="basis-80" />
                <select name="research_type" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All research types</option>
                    <option value="basic" @selected($filters['research_type'] === 'basic')>Basic Research</option>
                    <option value="action" @selected($filters['research_type'] === 'action')>Action Research</option>
                </select>
                <select name="authorship" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All authorship</option>
                    <option value="sole" @selected(($filters['authorship'] ?? '') === 'sole')>Sole Author</option>
                    <option value="collaborative" @selected(($filters['authorship'] ?? '') === 'collaborative')>Collaborative</option>
                </select>
                <select name="unit_type" aria-label="School or office category" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All school / office categories</option>
                    <option value="school" @selected($filters['unit_type'] === 'school')>School</option>
                    <option value="non_school" @selected($filters['unit_type'] === 'non_school')>Non-School</option>
                </select>
                <select name="organizational_unit" aria-label="School or office" class="max-w-xs rounded-xl border-slate-300 text-sm">
                    <option value="">All schools / offices</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school }}" @selected($filters['organizational_unit'] === $school)>{{ $school }}</option>
                    @endforeach
                </select>
                <select name="sort" class="rounded-xl border-slate-300 text-sm">
                    <option value="newest" @selected($filters['sort'] === 'newest')>Newest to oldest</option>
                    <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest to newest</option>
                </select>
            </x-filter-bar>

            <section>
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Proposal Research</h3>
                        <p class="text-xs text-slate-500">Proposals that passed review and were promoted into the completed-research phase.</p>
                    </div>
                </div>
                <x-repository.submission-table
                    :submissions="$approvedProposals"
                    badge-label="Proposal Approved"
                    stage="proposal"
                    :empty-message="$scope === 'own' ? 'None of your proposals have been approved yet.' : ($scope === 'reviewed' ? 'None of the proposals you reviewed have been approved yet.' : 'No approved proposals yet.')"
                    :filters="$filters"
                />
                <div class="mt-4">
                    {{ $approvedProposals->links() }}
                </div>
            </section>

            <section class="mt-10">
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Completed Research</h3>
                        <p class="text-xs text-slate-500">Finished research papers, fully approved and published to the repository.</p>
                    </div>
                </div>
                <x-repository.submission-table
                    :submissions="$completedResearch"
                    badge-label="Approved"
                    stage="completed"
                    :empty-message="$scope === 'own' ? 'You have no approved research yet.' : ($scope === 'reviewed' ? 'You have not reviewed any approved research yet.' : 'No approved research yet.')"
                    :filters="$filters"
                />
                <div class="mt-4">
                    {{ $completedResearch->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
