<?php
    // Shared by every role's manuscript-review route (researcher/reviewer/admin, both the
    // live document and a specific historical version — see ResearchSubmissionController,
    // ReviewerSubmissionController, and AdminSubmissionController's manuscript()/
    // manuscriptVersion() pairs), so resolving the version label once here covers all of
    // them instead of threading it through three separate controllers. $snapshotId is
    // always a concrete snapshot's id by the time it reaches this view — the "live" routes
    // resolve it to latestSnapshot()?->id rather than leaving it null.
    $viewedSnapshot = isset($snapshotId) ? $submission->snapshots()->find($snapshotId) : null;
    $latestSnapshot = $submission->latestSnapshot();
    $totalVersions = $submission->snapshots()->count();
?>
{{--
    x-focus-layout (no sidebar), not x-app-layout — manuscript viewing/review is a
    dedicated task in its own right, not a page within the app's usual section-to-section
    browsing. The header slot below already carries its own Back link back to the
    submission it belongs to, so nothing is lost by dropping the sidebar nav.
--}}
<x-focus-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Manuscript Review</p>
            </div>
            <a href="{{ $backUrl }}" class="text-sm font-medium text-cherry-700">Back</a>
        </div>
    </x-slot>

    @vite(['resources/js/pdf-review.js'])

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($viewedSnapshot)
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-cherry-50 px-3 py-1 text-sm font-medium text-cherry-700">
                        Version {{ $viewedSnapshot->version }} of {{ $totalVersions }}
                    </span>
                    @if ($latestSnapshot && $viewedSnapshot->is($latestSnapshot))
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">Current</span>
                    @else
                        <span class="text-sm text-slate-500">Viewing an earlier version — this reflects the manuscript as it was on {{ $viewedSnapshot->generated_at->format('M j, Y g:i A') }}.</span>
                    @endif
                </div>
            @endif
            @include('submissions.partials.pdf-viewer', [
                'submission' => $submission,
                'documentViewUrl' => $documentViewUrl,
                'commentsUrl' => $commentsUrl,
                'canCreate' => $canCreate,
                'canEditAll' => $canEditAll,
                'snapshotId' => $snapshotId ?? null,
            ])
        </div>
    </div>
</x-focus-layout>
