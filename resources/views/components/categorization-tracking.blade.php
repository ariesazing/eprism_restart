@props(['categorization', 'stages'])

@php
    $researchTypes = ['basic' => 'Basic Research', 'action' => 'Action Research'];
    $classifications = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];
@endphp

<section>
    <h3 class="text-lg font-semibold text-slate-900">Categorization Metrics</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($researchTypes as $typeKey => $typeLabel)
            @foreach ($classifications as $classKey => $classLabel)
                <div class="rounded-2xl border-l-4 {{ $classKey === 'proposal' ? 'border-sky-500 bg-sky-50' : 'border-emerald-500 bg-emerald-50' }} p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-xs uppercase tracking-[0.15em] text-slate-400">{{ $typeLabel }}</div>
                    <div class="mt-1 text-sm text-slate-600">{{ $classLabel }}</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $categorization["$typeKey:$classKey"] ?? 0 }}</div>
                </div>
            @endforeach
        @endforeach
    </div>
</section>

<section class="mt-8">
    <h3 class="text-lg font-semibold text-slate-900">Research Tracking</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border-l-4 border-blue-500 bg-blue-50 p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-500">Submitted</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline></svg>
                </span>
            </div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stages['submitted'] }}</div>
            <p class="mt-1 text-xs text-slate-400">Awaiting reviewer assignment</p>
        </div>
        <div class="rounded-2xl border-l-4 border-indigo-500 bg-indigo-50 p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-500">On Evaluation</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                    <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </span>
            </div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stages['on_evaluation'] }}</div>
            <p class="mt-1 text-xs text-slate-400">Under review by reviewers</p>
        </div>
        <div class="rounded-2xl border-l-4 border-emerald-500 bg-emerald-50 p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-500">Evaluated</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75 11.25 15 15 9.75"></path><circle cx="12" cy="12" r="9"></circle></svg>
                </span>
            </div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stages['evaluated'] }}</div>
            <p class="mt-1 text-xs text-slate-400">Approved by every reviewer</p>
        </div>
        <div class="rounded-2xl border-l-4 border-amber-500 bg-amber-50 p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-500">On Revision</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.9 2.7 17.1a1.5 1.5 0 0 0 1.3 2.3h16a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-2.6 0z"></path></svg>
                </span>
            </div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stages['on_revision'] }}</div>
            <p class="mt-1 text-xs text-slate-400">Returned to the researcher</p>
        </div>
    </div>
</section>
