@php
    $totalCount = $data['submissions']->count();
    $underReviewCount = ($data['statusCounts'][\App\Enums\SubmissionStatus::UNDER_REVIEW->value] ?? 0)
        + ($data['statusCounts'][\App\Enums\SubmissionStatus::SUBMITTED->value] ?? 0)
        + ($data['statusCounts'][\App\Enums\SubmissionStatus::RESUBMITTED->value] ?? 0);
    // Approved proposals + approved completed research — see DashboardController::researcherData().
    $approvedCount = $data['approvedTotal'];
    $draftCount = $data['statusCounts'][\App\Enums\SubmissionStatus::DRAFT->value] ?? 0;
    $revisionCount = $data['statusCounts'][\App\Enums\SubmissionStatus::REVISIONS_REQUIRED->value] ?? 0;
@endphp

<section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 2xl:grid-cols-5">
    <div data-tone="total" class="metric-card p-5 lg:col-span-2 2xl:col-span-1">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Total Submissions</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $totalCount }}</div>
        <p class="mt-1 text-xs text-slate-400">All research submissions you've created</p>
    </div>

    <div data-tone="draft" class="metric-card p-5 lg:col-span-2 2xl:col-span-1">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Drafts</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.9 3.6a2 2 0 0 1 2.8 2.8L8 18.1 4 19l.9-4L16.9 3.6z"></path><path d="M14.5 6 18 9.5"></path></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $draftCount }}</div>
        <p class="mt-1 text-xs text-slate-400">Not yet submitted for review</p>
    </div>

    <div data-tone="review" class="metric-card p-5 lg:col-span-2 2xl:col-span-1">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Under Review</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $underReviewCount }}</div>
        <p class="mt-1 text-xs text-slate-400">Submitted, under review, or resubmitted</p>
    </div>

    <div data-tone="approved" class="metric-card p-5 lg:col-span-3 2xl:col-span-1">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Approved</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75 11.25 15 15 9.75"></path><circle cx="12" cy="12" r="9"></circle></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $approvedCount }}</div>
        <p class="mt-1 text-xs text-slate-400">Approved proposals and completed research</p>
    </div>

    <div data-tone="attention" class="metric-card p-5 lg:col-span-3 2xl:col-span-1">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Revision Required</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                <svg class="h-4.5 w-4.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.9 2.7 17.1a1.5 1.5 0 0 0 1.3 2.3h16a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-2.6 0z"></path></svg>
            </span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $revisionCount }}</div>
        <p class="mt-1 text-xs text-slate-400">Needs your action before resubmission</p>
    </div>
</section>
