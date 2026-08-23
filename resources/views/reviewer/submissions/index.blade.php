<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Reviewer Queue</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('reviewer.submissions.index') }}" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
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
                    <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filter</button>
                    @if ($filters['search'] || $filters['status'] || $filters['research_type'] || $filters['classification'])
                        <a href="{{ route('reviewer.submissions.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </form>

            <div class="grid gap-4">
                @forelse ($submissions as $submission)
                    <a href="{{ route('reviewer.submissions.show', $submission) }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }}</p>
                            </div>
                            <x-status-badge :status="$submission->status" />
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">No assigned submissions.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>