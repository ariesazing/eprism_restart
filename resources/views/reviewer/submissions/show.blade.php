<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Researcher: {{ $submission->researcher->name }} · {{ $template->label }}</p>
            </div>
            <a href="{{ route('reviewer.submissions.index') }}" class="text-sm font-medium text-red-700">Back to queue</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8 lg:grid-cols-[1fr,0.9fr]">
            <section class="grid gap-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Manuscript</h3>
                        @if ($submission->snapshots->isNotEmpty())
                            <a href="{{ route('reviewer.submissions.manuscript.review', $submission) }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white">Open Manuscript &amp; Comments</a>
                        @else
                            <span class="text-sm text-slate-500">No manuscript generated yet.</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-slate-500">Review the full structured submission (all chapters) rendered in the standardized template, and leave sidebar comments without altering the document.</p>

                    @if ($submission->snapshots->isNotEmpty())
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <h4 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Version History</h4>
                            <p class="mt-1 text-xs text-slate-400">Earlier versions are read-only — open them to compare against the current revision.</p>
                            <div class="mt-3 grid gap-2">
                                @foreach ($submission->snapshots as $snapshot)
                                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-2 text-sm">
                                        <div>
                                            <span class="font-medium text-slate-900">Version {{ $snapshot->version }}</span>
                                            @if ($loop->first)
                                                <span class="ml-2 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Current</span>
                                            @endif
                                            <div class="text-xs text-slate-500">{{ $snapshot->generated_at->format('M j, Y g:i A') }} &middot; {{ $snapshot->generator->name ?? 'Unknown' }}</div>
                                        </div>
                                        <a href="{{ route('reviewer.submissions.manuscript.version', [$submission, $snapshot]) }}" target="_blank" class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Attachments</h3>
                    <div class="mt-4 grid gap-3">
                        @forelse ($submission->documents as $document)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
                                <a href="{{ route('reviewer.submissions.attachments.download', [$submission, $document]) }}" class="hover:underline">{{ $document->original_name }}</a>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">No attachments uploaded.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Rubric Scoring</h3>

                <form method="POST" action="{{ route('reviewer.submissions.review', $submission) }}" class="mt-4 grid gap-5">
                    @csrf
                    <fieldset class="grid gap-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach (['originality' => 'Originality', 'methodology' => 'Methodology', 'clarity' => 'Clarity', 'compliance' => 'Compliance'] as $field => $label)
                                <div>
                                    <label class="text-sm font-medium text-slate-700">{{ $label }}</label>
                                    <input type="number" min="1" max="5" name="{{ $field }}" value="{{ old($field, $existingReview->criteria_scores[$field] ?? 3) }}" class="mt-2 w-full rounded-xl border-slate-300" required />
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Recommendation</label>
                            <select name="recommendation" class="mt-2 w-full rounded-xl border-slate-300">
                                @foreach (['approve' => 'Approve', 'minor_revision' => 'Minor Revision', 'major_revision' => 'Major Revision'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('recommendation', $existingReview->recommendation ?? 'minor_revision') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Overall Comment</label>
                            <textarea name="comments" rows="8" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('comments', $existingReview->comments ?? '') }}</textarea>
                        </div>
                    </fieldset>
                    <button type="submit" class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-medium text-white">Submit Evaluation</button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
