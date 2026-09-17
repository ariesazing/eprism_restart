@props(['categorization'])

@php
    $researchTypes = ['basic' => 'Basic Research', 'action' => 'Action Research'];
    $classifications = ['proposal' => 'Proposal', 'completed' => 'Completed Research'];
@endphp

<section>
    <h3 class="text-lg font-semibold text-slate-900">Researches</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($researchTypes as $typeKey => $typeLabel)
            @foreach ($classifications as $classKey => $classLabel)
                <div data-tone="{{ $classKey === 'completed' ? 'approved' : 'attention' }}" class="metric-card p-5">
                    <div class="text-xs uppercase tracking-[0.15em] text-slate-400">{{ $typeLabel }}</div>
                    <div class="mt-1 text-sm text-slate-600">{{ $classLabel }}</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $categorization["$typeKey:$classKey"] ?? 0 }}</div>
                </div>
            @endforeach
        @endforeach
    </div>
</section>
