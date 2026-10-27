@props(['submission', 'badgeLabel', 'stage'])

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $isOwner = $user->id === $submission->researcher_id;
    $isReviewer = $user->isReviewer();
    $canViewManuscript = $isAdmin || $isOwner;
    $canViewDocuments = $canViewManuscript || $isReviewer;
    $classification = $stage === 'proposal' ? 'proposal' : 'completed';
    $approvedAt = $stage === 'proposal' ? $submission->proposal_approved_at : $submission->approved_at;
    $manuscriptUrl = null;

    if ($canViewManuscript) {
        if ($submission->usesManuscript()) {
            $version = $submission->approvedManuscriptVersion($classification);
            if ($version?->final_pdf_path) $manuscriptUrl = route('manuscript-versions.final', $version);
            elseif ($version?->research_snapshot_id) $manuscriptUrl = $isAdmin
                ? route('admin.submissions.manuscript.version', [$submission, $version->research_snapshot_id])
                : route('submissions.manuscript.version', [$submission, $version->research_snapshot_id]);
        } else {
            $snapshot = $submission->snapshotApprovedAsOf($approvedAt);
            $manuscriptUrl = $snapshot
                ? ($isAdmin ? route('admin.submissions.manuscript.version', [$submission, $snapshot]) : route('submissions.manuscript.version', [$submission, $snapshot]))
                : ($isAdmin ? route('admin.submissions.manuscript', $submission) : route('submissions.manuscript', $submission));
        }
    }

    $reviewSummary = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_REVIEW_SUMMARY);
    $routingSlip = $submission->latestRapmDocument(\App\Models\RapmDocument::KIND_ROUTING_SLIP);
    $evaluatedReviewers = $submission->allReviews->whereNotNull('submitted_at')->pluck('reviewer')->filter()->unique('id')->values();
    $detailsModal = 'repository-record-'.$submission->id.'-'.$stage;
@endphp

<button type="button" @click="$dispatch('open-modal', '{{ $detailsModal }}')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-cherry-300 hover:bg-cherry-50 hover:text-cherry-800">More</button>
<x-modal :name="$detailsModal" max-width="2xl">
    <div class="max-h-[85vh] overflow-y-auto">
        <header class="border-b border-slate-200 bg-slate-50 px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <span class="font-mono text-xs text-slate-500">{{ $submission->reference_code }}</span>
                    <h3 class="mt-1 text-xl font-semibold leading-snug text-slate-900">{{ $submission->title }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $submission->researcher?->name ?? 'Unknown researcher' }} · {{ ucfirst($submission->research_type) }} Research · {{ $stage === 'proposal' ? 'Approved proposal' : 'Completed research' }}</p>
                </div>
                <button type="button" @click="$dispatch('close-modal', '{{ $detailsModal }}')" aria-label="Close record details" class="rounded-lg p-2 text-slate-500 hover:bg-white">&times;</button>
            </div>
        </header>
        <div class="grid gap-5 p-6">
            <section class="rounded-xl border border-slate-200 p-4">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Record details</h4>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">School / office</dt><dd class="mt-1 font-medium text-slate-800">{{ $submission->organizational_unit ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Approved</dt><dd class="mt-1 font-medium text-slate-800">{{ $approvedAt?->format('M j, Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Authorship</dt><dd class="mt-1 font-medium text-slate-800">{{ $submission->authorshipLabel() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-1 font-medium text-slate-800">{{ $badgeLabel }}</dd></div>
                </dl>
            </section>
            <section class="rounded-xl border border-slate-200 p-4">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewers</h4>
                <p class="mt-2 text-sm text-slate-700">{{ $evaluatedReviewers->pluck('name')->join(', ') ?: 'No submitted evaluations recorded' }}</p>
            </section>
            @if ($canViewDocuments)
                <section class="rounded-xl border border-slate-200 p-4">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documents</h4>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($manuscriptUrl)<a href="{{ $manuscriptUrl }}" target="_blank" class="rounded-lg bg-cherry-700 px-3 py-2 text-sm font-medium text-white hover:bg-cherry-800">View Manuscript</a>@endif
                        @if ($reviewSummary)<a href="{{ route('rapm-documents.show', $reviewSummary) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Review Summary</a>@endif
                        @if ($routingSlip)<a href="{{ route('rapm-documents.show', $routingSlip) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Routing Slip</a>@endif
                        @unless ($manuscriptUrl || $reviewSummary || $routingSlip)<p class="text-sm text-slate-500">No documents are available for this record.</p>@endunless
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-modal>
