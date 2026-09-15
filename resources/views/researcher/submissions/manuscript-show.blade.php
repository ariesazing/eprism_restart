@php
    $editable = ! $submission->isLocked();
    $state = $submission->manuscript ?? [];
    $processing = in_array($state['state'] ?? '', ['waiting_for_save', 'processing'], true);
    $versions = $submission->manuscriptVersions()->with('snapshot')->latest('id')->get();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $submission->title }}</h2>
                <p class="text-sm text-slate-500">{{ $template->label }} · {{ $submission->status->label() }}</p>
            </div>
            <a href="{{ route('submissions.index') }}" class="text-sm text-cherry-700">Back to submissions</a>
        </div>
    </x-slot>
    @vite(['resources/js/manuscript.js', 'resources/js/submission-editor.js'])
    <div class="mx-auto grid max-w-6xl gap-6 px-6 py-8" data-manuscript-summary
        data-status-url="{{ route('submissions.manuscript.status', $submission) }}" data-processing="{{ $processing ? '1' : '0' }}">
        @if (session('status'))
            <p class="rounded-xl bg-emerald-50 p-4 text-emerald-800" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <ul class="rounded-xl bg-rose-50 p-4 text-rose-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        @if ($submission->admin_notes)
            <p class="whitespace-pre-wrap rounded-xl bg-amber-50 p-4 text-amber-900">{{ $submission->admin_notes }}</p>
        @endif
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-semibold">Manuscript</h3>
            <p data-manuscript-message role="status" class="my-3 text-sm text-slate-600">
                @if (($state['state'] ?? '') === 'waiting_for_save')
                    Waiting for the final save. Use “Save and return” in every open manuscript editor tab.
                @elseif ($processing)
                    Checking the saved document and preparing its review PDF. You can leave this page and return later.
                @elseif ($state['error'] ?? null)
                    {{ $state['error'] }}
                @else
                    Edit all chapters, tables, figures and references in one document. Close the editor before previewing or submitting.
                @endif
            </p>
            @if ($state['report']['errors'] ?? [])
                <ul class="mb-4 list-inside list-disc text-sm text-rose-700">
                    @foreach ($state['report']['errors'] as $finding)<li>{{ $finding }}</li>@endforeach
                </ul>
            @endif
            @if ($state['report']['warnings'] ?? [])
                <ul class="mb-4 list-inside list-disc text-sm text-amber-700">
                    @foreach ($state['report']['warnings'] as $finding)<li>{{ $finding }}</li>@endforeach
                </ul>
            @endif
            <div class="flex flex-wrap gap-4">
                @if ($editable)
                    <a href="{{ route('submissions.chapters', $submission) }}" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm text-white">Edit manuscript</a>
                @endif
                @if (isset($state['working_path']) && ! $processing && ! ($state['session_open'] ?? false))
                    <a href="{{ route('submissions.manuscript.review', $submission) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">View PDF</a>
                @endif
            </div>
        </div>

        @if (! isset($state['working_path']) && $versions->isEmpty() && $editable)
            <form method="POST" action="{{ route('submissions.update', $submission) }}" enctype="multipart/form-data" class="grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-slate-200" data-submission-form>
                @csrf @method('PUT')
                <h3 class="text-lg font-semibold">Research metadata</h3>
                <p class="text-sm text-slate-500">Check these details before opening the manuscript. They become fixed when its working document is created.</p>
                <label>Title <input class="mt-1 block w-full rounded-xl border-slate-300" name="title" value="{{ old('title', $submission->title) }}" required></label>
                <label>Research type
                    <select name="research_type" class="mt-1 block w-full rounded-xl border-slate-300">
                        <option value="basic" @selected($submission->research_type === 'basic')>Basic Research</option>
                        <option value="action" @selected($submission->research_type === 'action')>Action Research</option>
                    </select>
                </label>
                <input type="hidden" name="classification" value="{{ $submission->classification }}">
                @include('researcher.submissions.partials.organizational-unit-fields', [
                    'organizationalUnits' => $organizationalUnits, 'organizationalUnit' => $submission->organizational_unit,
                    'schoolId' => $submission->school_id, 'disabled' => false,
                ])
                <div data-proponents data-next-index="{{ $submission->proponents->count() }}" class="grid gap-3">
                    @foreach ($submission->proponents as $proponent)
                        @include('researcher.submissions.partials.proponent-fields', [
                            'index' => $loop->index, 'proponent' => $proponent->toArray(), 'lead' => $loop->first, 'disabled' => false,
                        ])
                    @endforeach
                </div>
                <button class="rounded-xl bg-cherry-700 px-4 py-2 text-white">Save metadata</button>
            </form>
        @else
            <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold">Research metadata</h3>
                <p class="mt-2 text-sm">{{ $submission->organizational_unit }} · {{ $submission->school_id }}</p>
                @foreach ($submission->proponents as $proponent)
                    <p class="mt-1 text-sm">{{ $proponent->first_name }} {{ $proponent->last_name }} · {{ $proponent->position }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('submissions.manuscript.attachments', $submission) }}" enctype="multipart/form-data" class="grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            @csrf
            <h3 class="text-lg font-semibold">Attachments</h3>
            @include('researcher.submissions.partials.attachments-editor', ['existing' => $submission->documents, 'disabled' => ! $editable])
            @if ($editable)<button class="rounded-xl bg-cherry-700 px-4 py-2 text-white">Save attachments</button>@endif
        </form>

        @if ($editable)
            <form method="POST" action="{{ $submission->status->value === 'revisions_required' ? route('submissions.resubmit', $submission) : route('submissions.submit', $submission) }}" class="rounded-2xl bg-cherry-50 p-6">
                @csrf
                <p class="mb-3 text-sm text-cherry-900">Submission saves a fixed DOCX version, checks its required sections and formatting, and prepares the PDF for reviewers.</p>
                @if ($submissionWindowOpen || $submission->status->value === 'revisions_required')
                    <button class="rounded-xl bg-cherry-700 px-5 py-2 text-white">{{ $submission->status->value === 'revisions_required' ? 'Resubmit' : 'Submit' }}</button>
                @else
                    <p class="text-sm">The submission window is closed. You can continue editing.</p>
                @endif
            </form>
        @endif

        <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            <h3 class="text-lg font-semibold">Document versions</h3>
            <ul class="mt-3 grid gap-3">
                @forelse ($versions as $version)
                    <li class="flex flex-wrap items-center gap-4 text-sm">
                        <span>{{ $version->metadata['classification'] === 'proposal' ? 'Proposal' : 'Completed research' }} · {{ $version->created_at->format('M j, Y g:i A') }} · {{ $version->state }}</span>
                        <a class="text-cherry-700 underline" href="{{ route('manuscript-versions.docx', $version) }}">DOCX</a>
                        @if ($version->snapshot)
                            <a class="text-cherry-700 underline" href="{{ route('submissions.manuscript.version.review', [$submission, $version->snapshot]) }}">Review PDF v{{ $version->snapshot->version }}</a>
                        @endif
                        @if ($version->final_pdf_path)
                            <a class="text-cherry-700 underline" href="{{ route('manuscript-versions.final', $version) }}">Approved PDF</a>
                        @elseif ($version->final_pdf_error)
                            <span class="text-rose-700">{{ $version->final_pdf_error }}</span>
                            <form method="POST" action="{{ route('manuscript-versions.retry-final', $version) }}">
                                @csrf
                                <button class="text-cherry-700 underline">Retry</button>
                            </form>
                        @elseif ($version->approved_at)
                            <span>Approved PDF is being prepared.</span>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Versions appear when you submit the manuscript.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            <h3 class="text-lg font-semibold">Review history</h3>
            @foreach ($submission->allReviews()->whereNotNull('submitted_at')->with('reviewer')->latest('submitted_at')->get() as $review)
                <p class="mt-3 text-sm font-medium">{{ $review->reviewer->name }} · {{ $review->recommendation }}</p>
                <p class="whitespace-pre-wrap text-sm text-slate-600">{{ $review->comments }}</p>
            @endforeach
        </div>
    </div>
    @include('researcher.submissions.partials.submission-form-script')
</x-app-layout>
