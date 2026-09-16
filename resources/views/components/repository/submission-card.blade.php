@props(['submission', 'badgeLabel', 'stage'])

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $isOwner = $user->id === $submission->researcher_id;
    $canViewDocuments = $isAdmin || $isOwner;

    // A proposal's classification is overwritten to 'completed' in place on promotion
    // (SubmissionDecisionService), so "the submission's current manuscript" no longer reliably
    // means "the approved proposal" once the same row has moved on to its completed-research
    // phase — this card always represents one specific stage ($stage), so its link has to be
    // resolved to *that* stage's own approved document, not whatever's current right now.
    $classification = $stage === 'proposal' ? 'proposal' : 'completed';
    $approvedAt = $stage === 'proposal' ? $submission->proposal_approved_at : $submission->approved_at;

    $manuscriptUrl = null;

    if ($canViewDocuments) {
        if ($submission->usesManuscript()) {
            $version = $submission->approvedManuscriptVersion($classification);

            if ($version?->final_pdf_path) {
                $manuscriptUrl = route('manuscript-versions.final', $version);
            } elseif ($version?->research_snapshot_id) {
                $manuscriptUrl = $isAdmin
                    ? route('admin.submissions.manuscript.version', [$submission, $version->research_snapshot_id])
                    : route('submissions.manuscript.version', [$submission, $version->research_snapshot_id]);
            }
            // No stage-specific version found at all: deliberately no fallback to the generic
            // "current" route here — that would silently reproduce the exact bug this fixes.
        } else {
            $snapshot = $submission->snapshotApprovedAsOf($approvedAt);

            $manuscriptUrl = $snapshot
                ? ($isAdmin
                    ? route('admin.submissions.manuscript.version', [$submission, $snapshot])
                    : route('submissions.manuscript.version', [$submission, $snapshot]))
                // No snapshot recorded as of the approval timestamp (shouldn't normally happen)
                // — fall back to the generic current-manuscript route rather than showing
                // nothing, same as this card behaved before this fix.
                : ($isAdmin
                    ? route('admin.submissions.manuscript', $submission)
                    : route('submissions.manuscript', $submission));
        }
    }

    $reviewSummary = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_REVIEW_SUMMARY);
    $routingSlip = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_ROUTING_SLIP);
@endphp

<article class="app-card bg-white p-6">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-slate-900">{{ $submission->title }}</h3>
        <div class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">{{ $badgeLabel }}</div>
    </div>
    <div class="mt-1 font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
    <p class="mt-2 text-sm text-slate-500">{{ $submission->researcher?->name ?? 'Unknown researcher' }} · {{ ucfirst($submission->research_type) }} Research</p>
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
