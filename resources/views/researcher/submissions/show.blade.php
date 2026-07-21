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
                @if ($errors->any())
                    <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Research Information</h3>
                    <form method="POST" action="{{ route('submissions.update', $submission) }}" enctype="multipart/form-data" class="mt-4 grid gap-5" data-submission-form>
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="text-sm font-medium text-slate-700">Title</label>
                            <input type="text" name="title" value="{{ old('title', $submission->title) }}" class="mt-2 w-full rounded-xl border-slate-300" data-title @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) required />
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Research Type</label>
                                <select name="research_type" class="mt-2 w-full rounded-xl border-slate-300" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) required>
                                    <option value="basic" @selected(old('research_type', $submission->research_type) === 'basic')>Basic Research</option>
                                    <option value="action" @selected(old('research_type', $submission->research_type) === 'action')>Action Research</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Classification</label>
                                <select name="classification" class="mt-2 w-full rounded-xl border-slate-300" data-classification @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) required>
                                    <option value="proposal" @selected(old('classification', $submission->classification) === 'proposal')>Proposal</option>
                                    <option value="completed" @selected(old('classification', $submission->classification) === 'completed')>Completed</option>
                                </select>
                                <p class="mt-2 text-xs text-slate-500">"Completed" unlocks only when the title matches a research already in the system; otherwise it stays locked to Proposal.</p>
                            </div>
                        </div>
                        @php $editable = in_array($submission->status->value, ['draft', 'revisions_required'], true); @endphp

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-slate-700">Proponents</label>
                                @if ($editable)
                                    <button type="button" class="text-sm font-medium text-cyan-700" data-add-proponent>+ Add proponent</button>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Proponent 1 is the researcher profile tied to this account. Add more if this research has co-proponents.</p>

                            <div class="mt-4 grid gap-4" data-proponents data-next-index="{{ $submission->proponents->count() }}">
                                @forelse ($submission->proponents as $proponent)
                                    @include('researcher.submissions.partials.proponent-fields', [
                                        'index' => $loop->index,
                                        'proponent' => $proponent->toArray(),
                                        'lead' => $loop->first,
                                        'disabled' => ! $editable,
                                        'organizationalUnits' => $organizationalUnits,
                                    ])
                                @empty
                                    @include('researcher.submissions.partials.proponent-fields', [
                                        'index' => 0,
                                        'proponent' => ['email' => auth()->user()->email],
                                        'lead' => true,
                                        'disabled' => ! $editable,
                                        'organizationalUnits' => $organizationalUnits,
                                    ])
                                @endforelse
                            </div>

                            @if ($editable)
                                <template data-proponent-template>
                                    @include('researcher.submissions.partials.proponent-fields', [
                                        'index' => '__INDEX__',
                                        'proponent' => [],
                                        'lead' => false,
                                        'disabled' => false,
                                        'organizationalUnits' => $organizationalUnits,
                                    ])
                                </template>
                            @endif
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Manuscript / Research Document</label>
                            <input type="file" name="manuscript" accept=".pdf,.doc,.docx" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" @disabled(! in_array($submission->status->value, ['draft', 'revisions_required'], true)) />
                            <p class="mt-2 text-xs text-slate-500">PDF, DOC, or DOCX. Required when submitting for review. Upload again to replace the current manuscript.</p>
                        </div>

                        @if (in_array($submission->status->value, ['draft', 'revisions_required'], true))
                            <div data-docs="proposal">
                                <label class="text-sm font-medium text-slate-700">Proposal Attachments</label>
                                <p class="mt-2 text-sm text-slate-500">Documentation and Narrative Form are required for proposals. Upload again to replace an existing file.</p>
                                <div class="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Documentation (PDF)</span>
                                        <input type="file" name="documents[documentation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Narrative Form (PDF)</span>
                                        <input type="file" name="documents[narrative_form]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                </div>
                            </div>

                            <div data-docs="completed">
                                <label class="text-sm font-medium text-slate-700">Completed Research Attachments</label>
                                <p class="mt-2 text-sm text-slate-500">Complete attachments are required for completed submissions. Upload again to replace an existing file.</p>
                                <div class="grid gap-6">
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Proposed Innovation / Intervention Material (PDF)</span>
                                        <input type="file" name="documents[proposed_innovation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Approval Proposal (PDF)</span>
                                        <input type="file" name="documents[approval_proposal]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Documentation (PDF)</span>
                                        <input type="file" name="documents[documentation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div>
                                            <span class="text-xs font-medium text-slate-500">Implementation — Accomplishment Report (PDF)</span>
                                            <input type="file" name="documents[implementation_accomplishment_report]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                        </div>
                                        <div>
                                            <span class="text-xs font-medium text-slate-500">Implementation — Certificate of Implementation (PDF)</span>
                                            <input type="file" name="documents[implementation_certificate_of_implementation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Dissemination (PDF)</span>
                                        <input type="file" name="documents[dissemination]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Adoption (PDF)</span>
                                        <input type="file" name="documents[adoption]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Utilization (PDF)</span>
                                        <input type="file" name="documents[utilization]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-slate-500">Liquidation (PDF)</span>
                                        <input type="file" name="documents[liquidation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if (in_array($submission->status->value, ['draft', 'revisions_required'], true))
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

    @include('researcher.submissions.partials.submission-form-script')
</x-app-layout>