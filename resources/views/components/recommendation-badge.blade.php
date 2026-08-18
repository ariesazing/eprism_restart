@props(['recommendation'])

@php
    [$label, $pillClasses, $dotClasses] = match ($recommendation) {
        'approve' => ['Approve', 'bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
        'minor_revision' => ['Minor Revision', 'bg-amber-50 text-amber-700', 'bg-amber-500'],
        'major_revision' => ['Major Revision', 'bg-rose-50 text-rose-700', 'bg-rose-500'],
        default => [str($recommendation)->replace('_', ' ')->headline()->toString(), 'bg-slate-100 text-slate-600', 'bg-slate-400'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold $pillClasses"]) }}>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotClasses }}"></span>
    {{ $label }}
</span>
