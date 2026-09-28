<div class="app-card bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Concerns &amp; Support Requests</h3>
            <p class="mt-1 text-sm text-slate-500">Need clarification or encountering an issue with this submission? Reach out to the research committee administrators.</p>
        </div>
        <div>
            <button type="button" @click="$dispatch('open-modal', 'submit-concern-modal')" class="inline-flex items-center gap-2 rounded-xl bg-cherry-700 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-cherry-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Report an Issue
            </button>
        </div>
    </div>

    @php
        $concerns = $submission->concerns()->with('responder')->latest('id')->get();
    @endphp

    <div class="mt-5 divide-y divide-slate-100">
        @forelse ($concerns as $concern)
            <div class="py-4 first:pt-0 last:pb-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase tracking-wider text-slate-600">{{ $concern->category }}</span>
                        <h4 class="font-semibold text-slate-900 text-sm">{{ $concern->subject }}</h4>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        @if ($concern->status === 'resolved')
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 font-semibold text-emerald-800">Resolved</span>
                        @elseif ($concern->status === 'in_progress')
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 font-semibold text-amber-800">In Progress</span>
                        @else
                            <span class="rounded-full bg-blue-100 px-2.5 py-0.5 font-semibold text-blue-800">Open</span>
                        @endif
                        <span>&middot; {{ $concern->created_at->format('M j, Y g:i A') }}</span>
                    </div>
                </div>
                <p class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{{ $concern->message }}</p>

                @if ($concern->admin_response)
                    <div class="mt-3 rounded-xl border border-cherry-100 bg-cherry-50/60 p-3.5 text-xs">
                        <div class="flex items-center justify-between gap-2 font-semibold text-cherry-900">
                            <span>Admin Response ({{ $concern->responder?->name ?? 'Administrator' }})</span>
                            <span class="text-slate-400 font-normal">{{ $concern->responded_at?->format('M j, Y g:i A') }}</span>
                        </div>
                        <p class="mt-1 text-slate-800 whitespace-pre-wrap">{{ $concern->admin_response }}</p>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-2 text-xs text-slate-500">No support requests or issues reported yet for this submission.</p>
        @endforelse
    </div>
</div>

<x-modal name="submit-concern-modal" max-width="md" focusable>
    <form method="POST" action="{{ route('submissions.concerns.store', $submission) }}" class="p-6">
        @csrf
        <h3 class="text-lg font-semibold text-slate-900">Report an Issue or Concern</h3>
        <p class="mt-1 text-xs text-slate-500">Submit your concern directly to the administrators reviewing this submission.</p>

        <div class="mt-4 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Category</label>
                <select name="category" required class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cherry-500 focus:ring-cherry-500">
                    <option value="general">General Inquiry</option>
                    <option value="technical">Technical / System Problem</option>
                    <option value="rubric">Evaluation / Rubric Question</option>
                    <option value="deadline">Timeline / Deadline Extension</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Subject</label>
                <input type="text" name="subject" required maxlength="255" placeholder="Summary of your concern" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cherry-500 focus:ring-cherry-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Message</label>
                <textarea name="message" required rows="4" maxlength="5000" placeholder="Describe the details of your inquiry or issue..." class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cherry-500 focus:ring-cherry-500"></textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" @click="$dispatch('close-modal', 'submit-concern-modal')" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cherry-800">Submit Concern</button>
        </div>
    </form>
</x-modal>
