@props(['submission', 'badgeLabel'])

@php
    $user = auth()->user();
    $isOwner = $user->id === $submission->researcher_id;
    $canViewDocuments = $user->isAdmin() || $isOwner;

    $manuscriptUrl = $user->isAdmin()
        ? route('admin.submissions.manuscript', $submission)
        : ($isOwner ? route('submissions.manuscript', $submission) : null);

    $reviewSummary = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_REVIEW_SUMMARY);
    $routingSlip = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_ROUTING_SLIP);
@endphp

<article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
        <div class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">{{ $badgeLabel }}</div>
    </div>
    <div class="mt-1 font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
    <p class="mt-2 text-sm text-slate-500">{{ $submission->researcher->name }} · {{ ucfirst($submission->research_type) }} Research</p>
    <div class="mt-4 text-sm text-slate-500">Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'Not assigned' }}</div>

    @if ($canViewDocuments && ($manuscriptUrl || $reviewSummary || $routingSlip))
        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            @if ($manuscriptUrl)
                <a href="{{ $manuscriptUrl }}" target="_blank" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View Manuscript</a>
            @endif
            @if ($reviewSummary)
                <a href="{{ route('rapm-documents.show', $reviewSummary) }}" target="_blank" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Review Summary</a>
            @endif
            @if ($routingSlip)
                <a href="{{ route('rapm-documents.show', $routingSlip) }}" target="_blank" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Routing Slip</a>
            @endif
        </div>
    @endif
</article>
