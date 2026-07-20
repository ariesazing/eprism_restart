<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">My Research Submissions</h2>
            <a href="{{ route('submissions.create') }}" class="rounded-full bg-cyan-700 px-4 py-2 text-sm font-medium text-white">New Submission</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4">
                @forelse ($submissions as $submission)
                    <a href="{{ route('submissions.show', $submission) }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $submission->course }} · Reviewer: {{ $submission->reviewer->name ?? 'Unassigned' }}</p>
                            </div>
                            <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">{{ $submission->status->label() }}</div>
                        </div>
                        <p class="mt-4 text-sm text-slate-600">{{ $submission->abstract }}</p>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">No submissions yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>