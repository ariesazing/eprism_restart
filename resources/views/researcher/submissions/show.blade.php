@php
    $editable = ! $submission->isLocked();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $template->label }} &middot; {{ $submission->status->label() }} &middot; Reviewers: {{ $submission->reviewers->pluck('name')->join(', ') ?: 'Unassigned' }}</p>
            </div>
            <div class="flex items-center gap-4">
                {{-- Always available: a submitted submission views its immutable snapshot, a
                     draft (or a revision still being reworked) views a live preview of its
                     current content — see ResearchSubmissionController::streamManuscript(). --}}
                <a href="{{ route('submissions.manuscript.review', $submission) }}" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">View Manuscript</a>
                <a href="{{ route('submissions.index') }}" class="text-sm font-medium text-cherry-700">Back to submissions</a>
            </div>
        </div>
    </x-slot>

    @vite(['resources/js/submission-editor.js'])

    <div class="py-10">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:px-8">
            @if ($submission->status->value === 'revisions_required')
                <div class="rounded-2xl bg-amber-50 p-6 shadow-sm ring-1 ring-amber-200">
                    <h3 class="text-lg font-semibold text-amber-900">Revisions Required</h3>
                    <p class="mt-2 text-sm text-amber-700">{{ $submission->admin_notes }}</p>
                </div>
            @endif

            @unless ($editable)
                <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200">
                    This submission is read-only while it is {{ strtolower($submission->status->label()) }}.
                </div>
            @endunless

            <form method="POST" action="{{ route('submissions.update', $submission) }}" enctype="multipart/form-data" class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" data-submission-form data-section-editor-form @if ($editable) data-autosave-url="{{ route('submissions.autosave', $submission) }}" @endif>
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-slate-700">Title</label>
                    <input type="text" name="title" value="{{ old('title', $submission->title) }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! $editable) required />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Research Type</label>
                    <select name="research_type" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! $editable) required>
                        <option value="basic" @selected(old('research_type', $submission->research_type) === 'basic')>Basic Research</option>
                        <option value="action" @selected(old('research_type', $submission->research_type) === 'action')>Action Research</option>
                    </select>
                </div>
                <input type="hidden" name="classification" value="{{ $submission->classification }}" />

                @include('researcher.submissions.partials.organizational-unit-fields', [
                    'organizationalUnits' => $organizationalUnits,
                    'organizationalUnit' => $submission->organizational_unit,
                    'schoolId' => $submission->school_id,
                    'disabled' => ! $editable,
                ])

                <div>
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-slate-700">Proponents</label>
                        @if ($editable)
                            <button type="button" class="text-sm font-medium text-cherry-700" data-add-proponent>+ Add proponent</button>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-4" data-proponents data-next-index="{{ $submission->proponents->count() }}">
                        @foreach ($submission->proponents as $proponent)
                            @include('researcher.submissions.partials.proponent-fields', [
                                'index' => $loop->index,
                                'proponent' => $proponent->toArray(),
                                'lead' => $loop->first,
                                'disabled' => ! $editable,
                            ])
                        @endforeach
                    </div>

                    @if ($editable)
                        <template data-proponent-template>
                            @include('researcher.submissions.partials.proponent-fields', [
                                'index' => '__INDEX__',
                                'proponent' => [],
                                'lead' => false,
                                'disabled' => false,
                            ])
                        </template>
                    @endif
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Chapters</h3>
                    <p class="mt-1 text-sm text-slate-500">Fill in each chapter for the {{ $template->label }} template.</p>
                    @include('researcher.submissions.partials.section-editor', [
                        'template' => $template,
                        'sections' => $sections,
                        'disabled' => ! $editable,
                    ])
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Attachments</h3>
                    @include('researcher.submissions.partials.attachments-editor', [
                        'template' => $template,
                        'existing' => $submission->documents,
                        'disabled' => ! $editable,
                    ])
                </div>

                @if ($editable)
                    <button type="submit" class="rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save</button>
                @endif
            </form>

            @if ($submission->status->value === 'draft')
                <form method="POST" action="{{ route('submissions.submit', $submission) }}" class="rounded-2xl bg-cherry-50 p-6 shadow-sm ring-1 ring-cherry-200">
                    @csrf
                    <h3 class="text-lg font-semibold text-cherry-900">Submit for Review</h3>
                    <p class="mt-2 text-sm text-cherry-700">Save your chapters and attachments first, then finalize this draft for the reviewer queue.</p>
                    <button type="submit" class="mt-4 rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Submit</button>
                </form>
            @endif

            @if ($submission->status->value === 'revisions_required')
                <form method="POST" action="{{ route('submissions.resubmit', $submission) }}" class="rounded-2xl bg-amber-50 p-6 shadow-sm ring-1 ring-amber-200">
                    @csrf
                    <h3 class="text-lg font-semibold text-amber-900">Resubmit for Review</h3>
                    <p class="mt-2 text-sm text-amber-700">Save your changes above first, then resubmit.</p>
                    <button type="submit" class="mt-4 rounded-full bg-amber-500 px-5 py-2.5 text-sm font-medium text-white">Resubmit</button>
                </form>
            @endif

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Review History</h3>
                <div class="mt-4 grid gap-4">
                    @forelse ($submission->reviews->whereNotNull('submitted_at') as $review)
                        <div class="rounded-xl bg-slate-50 p-4">
                            <div class="font-medium text-slate-900">{{ $review->reviewer->name }}</div>
                            <div class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-500">{{ str($review->recommendation)->replace('_', ' ')->headline() }}</div>
                            <p class="mt-3 text-sm text-slate-600">{{ $review->comments }}</p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">No reviewer feedback yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @include('researcher.submissions.partials.submission-form-script')
</x-app-layout>
