<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">My Research Submissions</h2>
            <a href="{{ route('submissions.create') }}" class="rounded-full bg-red-700 px-4 py-2 text-sm font-medium text-white">New Submission</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('submissions.index') }}" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title or reference" class="w-56 flex-1 rounded-xl border-slate-300 text-sm" />
                <select name="status" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\SubmissionStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
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
                    @if ($filters['search'] || $filters['status'] || $filters['research_type'] || $filters['classification'])
                        <a href="{{ route('submissions.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </form>

            <div class="grid gap-4" x-data="{ sram: { loading: false, error: null, title: '', data: null } }">
                @forelse ($submissions as $submission)
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <a href="{{ route('submissions.show', $submission) }}" class="block">
                                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }} &middot; Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'Unassigned' }}</p>
                            </a>
                            <x-status-badge :status="$submission->status" />
                        </div>
                        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4">
                            <a href="{{ route('submissions.show', $submission) }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                            <button
                                type="button"
                                @click="
                                    sram.loading = true; sram.error = null; sram.data = null; sram.title = @js($submission->title);
                                    $dispatch('open-modal', 'sram-result');
                                    fetch('{{ route('submissions.sram', $submission) }}', { headers: { 'Accept': 'application/json' } })
                                        .then((r) => { if (! r.ok) throw new Error('failed'); return r.json(); })
                                        .then((json) => { sram.data = json; })
                                        .catch(() => { sram.error = 'Unable to load the Submission Readiness Assessment. Try again later.'; })
                                        .finally(() => { sram.loading = false; });
                                "
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75 11.25 15 15 9.75" /><path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z" /></svg>
                                SRAM
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">No submissions yet.</div>
                @endforelse

                <x-modal name="sram-result" max-width="lg">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Submission Readiness Assessment</h3>
                                <p class="mt-1 text-sm text-slate-500" x-text="sram.title"></p>
                            </div>
                            <button type="button" @click="$dispatch('close-modal', 'sram-result')" class="rounded-md p-1 text-slate-400 hover:bg-slate-100">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <template x-if="sram.loading">
                            <div class="mt-6 text-sm text-slate-500">Running the assessment&hellip;</div>
                        </template>

                        <template x-if="sram.error">
                            <div class="mt-6 rounded-xl bg-rose-50 p-4 text-sm text-rose-700" x-text="sram.error"></div>
                        </template>

                        <template x-if="sram.data && ! sram.loading">
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-sm text-slate-500">Completeness</div>
                                    <div class="mt-2 text-3xl font-semibold text-slate-900" x-text="sram.data.completeness_percent + '%'"></div>
                                    <div class="mt-1 text-xs text-slate-400">
                                        <span x-text="sram.data.sections.done + '/' + sram.data.sections.total + ' chapters'"></span>
                                        &middot;
                                        <span x-text="sram.data.attachments.done + '/' + sram.data.attachments.total + ' attachments'"></span>
                                    </div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-sm text-slate-500">Grammar Correctness</div>
                                    <div class="mt-2 text-3xl font-semibold text-slate-900" x-text="sram.data.grammar_available ? sram.data.grammar_percent + '%' : '—'"></div>
                                    <div class="mt-1 text-xs text-slate-400" x-text="sram.data.grammar_available ? (sram.data.issue_count + ' issue(s) across ' + sram.data.word_count + ' words') : 'Grammar service unavailable'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </x-modal>
            </div>
        </div>
    </div>
</x-app-layout>