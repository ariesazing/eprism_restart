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
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold uppercase tracking-[0.1em] text-slate-500">{{ $typeLabel }}</div>
                            <div class="mt-1 text-base font-bold text-slate-900">{{ $classLabel }}</div>
                        </div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-700">{{ $categorization["$typeKey:$classKey"] ?? 0 }}</span>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</section>
