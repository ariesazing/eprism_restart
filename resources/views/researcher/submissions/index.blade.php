<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">My Research Submissions</h2>
            <a href="{{ route('submissions.create') }}" class="rounded-full bg-red-700 px-4 py-2 text-sm font-medium text-white">New Submission</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('submissions.index') }}" class="mb-6 grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-5">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title or reference" class="rounded-xl border-slate-300 text-sm md:col-span-2" />
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
                <div class="flex gap-2 md:col-span-5">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
                    @if ($filters['search'] || $filters['status'] || $filters['research_type'] || $filters['classification'])
                        <a href="{{ route('submissions.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </form>

            <div class="grid gap-4">
                @forelse ($submissions as $submission)
                    <a href="{{ route('submissions.show', $submission) }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }} &middot; Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'Unassigned' }}</p>
                            </div>
                            <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">{{ $submission->status->label() }}</div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">No submissions yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>