@props(['status'])

@php
    $enumStatus = $status instanceof \App\Enums\SubmissionStatus ? $status : (\App\Enums\SubmissionStatus::tryFrom($status) ?? $status);
    $statusValue = $enumStatus instanceof \App\Enums\SubmissionStatus ? $enumStatus->value : (string) $enumStatus;
    $label = $enumStatus instanceof \App\Enums\SubmissionStatus ? $enumStatus->label() : ucfirst(str_replace('_', ' ', $statusValue));

    // Urgency levels with distinct shapes and color palettes:
    // - Urgent / Action needed: rounded-md (alert shape)
    // - Active queue / In-progress: rounded-lg (focused processing shape)
    // - Approved / Neutral: rounded-full (calm pill shape)
    [$pillClasses, $iconType] = match ($statusValue) {
        \App\Enums\SubmissionStatus::REVISIONS_REQUIRED->value => ['rounded-md bg-amber-50 text-amber-900 border border-amber-300 ring-1 ring-amber-200/50 shadow-xs', 'alert'],
        \App\Enums\SubmissionStatus::UNDER_REVIEW->value => ['rounded-lg bg-indigo-50 text-indigo-800 border border-indigo-200 shadow-xs', 'pulse'],
        \App\Enums\SubmissionStatus::SUBMITTED->value => ['rounded-lg bg-blue-50 text-blue-800 border border-blue-200 shadow-xs', 'dot_blue'],
        \App\Enums\SubmissionStatus::RESUBMITTED->value => ['rounded-lg bg-purple-50 text-purple-800 border border-purple-200 shadow-xs', 'dot_purple'],
        \App\Enums\SubmissionStatus::APPROVED->value => ['rounded-full bg-emerald-50 text-emerald-800 border border-emerald-300 shadow-xs', 'check'],
        default => ['rounded-full bg-slate-100 text-slate-700 border border-slate-200 shadow-xs', 'dot_slate'],
    };
@endphp

<span {{ $attributes->merge(['class' => "research-status inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-1 text-xs font-semibold $pillClasses"]) }}>
    @if ($iconType === 'alert')
        <svg class="h-3.5 w-3.5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    @elseif ($iconType === 'check')
        <svg class="h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
    @elseif ($iconType === 'pulse')
        <span class="relative flex h-2 w-2 shrink-0">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-600"></span>
        </span>
    @elseif ($iconType === 'dot_blue')
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600"></span>
    @elseif ($iconType === 'dot_purple')
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-purple-600"></span>
    @else
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400"></span>
    @endif
    <span>{{ $label }}</span>
</span>
