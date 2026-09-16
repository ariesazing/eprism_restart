@php
    $revisions = $data['submissions']->where('status', \App\Enums\SubmissionStatus::REVISIONS_REQUIRED);
    $drafts = $data['submissions']->where('status', \App\Enums\SubmissionStatus::DRAFT);
    $nextSubmission = $revisions->first() ?? $drafts->first();
@endphp

<x-dashboard-priority
    eyebrow="Your next step"
    :title="$revisions->isNotEmpty() ? $revisions->count().' submissions need revision' : ($drafts->isNotEmpty() ? 'Continue your research' : 'Ready for your next research milestone')"
    :description="$nextSubmission ? 'Pick up where you left off: '.$nextSubmission->title : 'Track your research progress below or start a new submission.'"
    :href="$nextSubmission ? route('submissions.show', $nextSubmission) : route('submissions.create')"
    :action="$revisions->isNotEmpty() ? 'Review requested changes' : ($drafts->isNotEmpty() ? 'Continue draft' : 'New submission')"
/>

@include('dashboard.researcher._metrics', ['data' => $data])

<section class="grid items-start gap-6 lg:grid-cols-2">
    @include('dashboard.researcher._readiness', ['data' => $data])
    @include('dashboard.researcher._feedback', ['data' => $data])
</section>

@include('dashboard.researcher._tracking', ['data' => $data])

@include('dashboard.researcher._activity', ['data' => $data])
