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

            <div x-data="{ repositoryFiltersOpen: true }" class="mb-8">
                <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                <button
                    type="button"
                    @click="repositoryFiltersOpen = ! repositoryFiltersOpen"
                    :aria-expanded="repositoryFiltersOpen.toString()"
                    aria-controls="repository-filter-panel"
                    class="flex w-full items-center justify-between gap-4 bg-amber-50 px-5 py-4 text-left transition hover:bg-amber-100/70"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-amber-700 shadow-sm ring-1 ring-amber-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10m-7 6h4" /></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">Refine research</span>
                            <span class="mt-0.5 block text-xs font-normal text-slate-600">Search and narrow results by type, school, authorship, and approval date.</span>
                        </span>
                    </span>
                    <span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-amber-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <span x-text="repositoryFiltersOpen ? 'Hide filters' : 'Show filters'"></span>
                        <svg class="h-4 w-4 text-amber-700 transition-transform duration-200" :class="{ 'rotate-180': repositoryFiltersOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </span>
                </button>
                <div id="repository-filter-panel" x-show="repositoryFiltersOpen" x-transition.opacity.duration.150ms class="border-t border-amber-100 p-4 sm:p-5">
                    <x-filter-bar
                        :action="route('repository.index')"
                        :has-active-filters="(bool) ($filters['organizational_unit'] || $filters['unit_type'] || $filters['search'] || $filters['research_type'] || !empty($filters['authorship']) || $filters['date_from'] || $filters['date_to'] || $filters['sort'] !== 'newest')"
                        :clear-url="route('repository.index')"
                        clear-label="Clear all"
                        submit-label="Apply filters"
                        form-class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 xl:grid-cols-4"
                        class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5"
                    >
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">Search</label>
                            <x-table-search :value="$filters['search']" placeholder="Title or researcher" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500" />
                        </div>
                        <div>
                            <label for="repository-research-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">Research type</label>
                            <select id="repository-research-type" name="research_type" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">All types</option>
                                <option value="basic" @selected($filters['research_type'] === 'basic')>Basic Research</option>
                                <option value="action" @selected($filters['research_type'] === 'action')>Action Research</option>
                            </select>
                        </div>
                        <div>
                            <label for="repository-authorship" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">Authorship</label>
                            <select id="repository-authorship" name="authorship" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">All authors</option>
                                <option value="sole" @selected(($filters['authorship'] ?? '') === 'sole')>Sole Author</option>
                                <option value="collaborative" @selected(($filters['authorship'] ?? '') === 'collaborative')>Collaborative</option>
                            </select>
                        </div>
                        <div>
                            <label for="repository-unit-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">School / office category</label>
                            <select id="repository-unit-type" name="unit_type" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">All categories</option>
                                <option value="school" @selected($filters['unit_type'] === 'school')>School</option>
                                <option value="non_school" @selected($filters['unit_type'] === 'non_school')>Non-School</option>
                            </select>
                        </div>
                        <div>
                            <label for="repository-unit" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">School / office</label>
                            <select id="repository-unit" name="organizational_unit" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">All schools / offices</option>
                                @foreach ($schools as $school)
                                    <option value="{{ $school }}" @selected($filters['organizational_unit'] === $school)>{{ $school }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="repository-sort" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">Sort by approval</label>
                            <select id="repository-sort" name="sort" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                                <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                            </select>
                        </div>
                        <div>
                            <label for="repository-date-from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">Approved from</label>
                            <input id="repository-date-from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label for="repository-date-to" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">Approved to</label>
                            <input id="repository-date-to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </x-filter-bar>
                </div>
                </div>
            </div>

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
