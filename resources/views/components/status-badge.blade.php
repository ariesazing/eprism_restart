@props(['status'])

@php
    $colors = match ($status) {
        \App\Enums\SubmissionStatus::DRAFT => ['bg-slate-100 text-slate-600', 'bg-slate-400'],
        \App\Enums\SubmissionStatus::SUBMITTED => ['bg-blue-50 text-blue-700', 'bg-blue-500'],
        \App\Enums\SubmissionStatus::UNDER_REVIEW => ['bg-indigo-50 text-indigo-700', 'bg-indigo-500'],
        \App\Enums\SubmissionStatus::REVISIONS_REQUIRED => ['bg-amber-50 text-amber-700', 'bg-amber-500'],
        \App\Enums\SubmissionStatus::RESUBMITTED => ['bg-purple-50 text-purple-700', 'bg-purple-500'],
        \App\Enums\SubmissionStatus::APPROVED => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
    };
    [$pillClasses, $dotClasses] = $colors;
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold $pillClasses"]) }}>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotClasses }}"></span>
    {{ $status->label() }}
</span>
