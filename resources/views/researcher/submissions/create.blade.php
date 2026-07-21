<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Create Submission</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" data-submission-form>
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" class="mt-2 w-full rounded-xl border-slate-300" data-title required />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Research Type</label>
                        <select name="research_type" class="mt-2 w-full rounded-xl border-slate-300" required>
                            <option value="basic" @selected(old('research_type') === 'basic')>Basic Research</option>
                            <option value="action" @selected(old('research_type') === 'action')>Action Research</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Classification</label>
                    <select name="classification" class="mt-2 w-full rounded-xl border-slate-300" data-classification required>
                        <option value="proposal" @selected(old('classification') === 'proposal')>Proposal</option>
                        <option value="completed" @selected(old('classification') === 'completed')>Completed</option>
                    </select>
                    <p class="mt-2 text-xs text-slate-500">If the title does not match an existing submission, this is locked to Proposal. "Completed" unlocks once the title matches a research already in the system.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Proponents</h3>
                        <button type="button" class="text-sm font-medium text-cyan-700" data-add-proponent>+ Add proponent</button>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Proponent 1 is your own researcher profile. Add more if this research has co-proponents.</p>

                    <div class="mt-4 grid gap-4" data-proponents data-next-index="1">
                        @include('researcher.submissions.partials.proponent-fields', [
                            'index' => 0,
                            'proponent' => ['email' => auth()->user()->email],
                            'lead' => true,
                            'disabled' => false,
                            'organizationalUnits' => $organizationalUnits,
                        ])
                    </div>

                    <template data-proponent-template>
                        @include('researcher.submissions.partials.proponent-fields', [
                            'index' => '__INDEX__',
                            'proponent' => [],
                            'lead' => false,
                            'disabled' => false,
                            'organizationalUnits' => $organizationalUnits,
                        ])
                    </template>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Manuscript / Research Document</label>
                    <input type="file" name="manuscript" accept=".pdf,.doc,.docx" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                    <p class="mt-2 text-xs text-slate-500">PDF, DOC, or DOCX. Required when submitting for review.</p>
                </div>

                <div data-docs="proposal">
                    <h3 class="text-lg font-semibold text-slate-900">Proposal Attachments</h3>
                    <p class="mt-2 text-sm text-slate-500">Required for proposal submissions (basic or action research).</p>
                    <div class="mt-4 grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Documentation (PDF)</label>
                            <input type="file" name="documents[documentation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Narrative Form (PDF)</label>
                            <input type="file" name="documents[narrative_form]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                    </div>
                </div>

                <div data-docs="completed">
                    <h3 class="text-lg font-semibold text-slate-900">Completed Research Attachments</h3>
                    <p class="mt-2 text-sm text-slate-500">Required for completed research submissions (basic or action research).</p>
                    <div class="mt-4 grid gap-6">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Proposed Innovation / Intervention Material (PDF)</label>
                            <input type="file" name="documents[proposed_innovation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Approval Proposal (PDF)</label>
                            <input type="file" name="documents[approval_proposal]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Documentation (PDF)</label>
                            <input type="file" name="documents[documentation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Implementation — Accomplishment Report (PDF)</label>
                                <input type="file" name="documents[implementation_accomplishment_report]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Implementation — Certificate of Implementation (PDF)</label>
                                <input type="file" name="documents[implementation_certificate_of_implementation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Dissemination (PDF)</label>
                            <input type="file" name="documents[dissemination]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Adoption (PDF)</label>
                            <input type="file" name="documents[adoption]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Utilization (PDF)</label>
                            <input type="file" name="documents[utilization]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Liquidation (PDF)</label>
                            <input type="file" name="documents[liquidation]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="action" value="draft" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700">Save Draft</button>
                    <button type="submit" name="action" value="submit" class="rounded-full bg-cyan-700 px-5 py-2.5 text-sm font-medium text-white">Submit for Review</button>
                </div>
            </form>
        </div>
    </div>

    @include('researcher.submissions.partials.submission-form-script')
</x-app-layout>
