<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $submission->status->label() }} · Reviewer: {{ $submission->reviewer->name ?? 'Unassigned' }}</p>
            </div>
            <a href="{{ route('submissions.index') }}" class="text-sm font-medium text-cyan-700">Back to submissions</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8 lg:grid-cols-[1.2fr,0.8fr]">
            <section class="grid gap-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Research Information</h3>
                    <form method="POST" action="{{ route('submissions.update', $submission) }}" enctype="multipart/form-data" class="mt-4 grid gap-5">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="text-sm font-medium text-slate-700">Title</label>
                            <input type="text" name="title" value="{{ old('title', $submission->title) }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) />
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Course / Department</label>
                                <input type="text" name="course" value="{{ old('course', $submission->course) }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Keywords</label>
                                <input type="text" name="keywords" value="{{ old('keywords', $submission->keywords) }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) />
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Authors</label>
                            <textarea name="authors" rows="2" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true))>{{ old('authors', $submission->authors) }}</textarea>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Abstract</label>
                            <textarea name="abstract" rows="6" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true))>{{ old('abstract', $submission->abstract) }}</textarea>
                        </div>
                        @if (in_array($submission->status->value, ['draft', 'revisions_required'], true))
                            <div>
                                <label class="text-sm font-medium text-slate-700">Upload Updated Documents</label>
                                <input type="file" name="documents[]" multiple class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                            </div>
                            <button type="submit" class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-medium text-white">Save Updates</button>
                        @endif
                    </form>
                </div>

                @if ($submission->status->value === 'draft')
                    <form method="POST" action="{{ route('submissions.submit', $submission) }}" class="rounded-2xl bg-cyan-50 p-6 shadow-sm ring-1 ring-cyan-200">
                        @csrf
                        <h3 class="text-lg font-semibold text-cyan-900">Submit for Review</h3>
                        <p class="mt-2 text-sm text-cyan-700">Finalize this draft and move it into the reviewer assignment queue.</p>
                        <button type="submit" class="mt-4 rounded-full bg-cyan-700 px-5 py-2.5 text-sm font-medium text-white">Submit</button>
                    </form>
                @endif

                @if ($submission->status->value === 'revisions_required')
                    <form method="POST" action="{{ route('submissions.revision', $submission) }}" enctype="multipart/form-data" class="rounded-2xl bg-amber-50 p-6 shadow-sm ring-1 ring-amber-200">
                        @csrf
                        <h3 class="text-lg font-semibold text-amber-900">Revision Submission</h3>
                        <p class="mt-2 text-sm text-amber-700">Administrator notes: {{ $submission->admin_notes }}</p>
                        <input type="file" name="revision_document" class="mt-4 block w-full rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm" required />
                        <button type="submit" class="mt-4 rounded-full bg-amber-500 px-5 py-2.5 text-sm font-medium text-white">Send Revision</button>
                    </form>
                @endif
            </section>

            <section class="grid gap-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Documents</h3>
                    <div class="mt-4 grid gap-3">
                        @forelse ($submission->documents as $document)
                            <a href="{{ route('submissions.documents.download', [$submission, $document]) }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">{{ $document->document_type }} · {{ $document->original_name }}</a>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">No documents uploaded.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Review History</h3>
                    <div class="mt-4 grid gap-4">
                        @forelse ($submission->reviews as $review)
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
            </section>
        </div>
    </div>
</x-app-layout>