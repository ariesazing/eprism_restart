<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Research Repository</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-2">
                @forelse ($submissions as $submission)
                    <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
                            <div class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Approved</div>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ $submission->course }}</p>
                        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $submission->abstract }}</p>
                        <div class="mt-4 text-sm text-slate-500">Reviewer: {{ $submission->reviewer->name ?? 'Not assigned' }}</div>
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">No approved research has been published yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>