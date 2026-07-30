<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Researcher: {{ $submission->researcher->name }} · {{ $template->label }}</p>
            </div>
            <a href="{{ route('reviewer.submissions.index') }}" class="text-sm font-medium text-cyan-700">Back to queue</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8 lg:grid-cols-[1fr,0.9fr]">
            <section class="grid gap-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Manuscript</h3>
                        @if ($submission->latestSnapshot())
                            <a href="{{ route('reviewer.submissions.manuscript.review', $submission) }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white">Open Manuscript &amp; Comments</a>
                        @else
                            <span class="text-sm text-slate-500">No manuscript generated yet.</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-slate-500">Review the full structured submission (all chapters) rendered in the standardized template, and leave sidebar comments without altering the document.</p>
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
                            <label class="text-sm font-medium text-slate-700">Comments</label>
                            <textarea name="comments" rows="8" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('comments', $existingReview->comments ?? '') }}</textarea>
                        </div>
                    </fieldset>
                    <button type="submit" class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-medium text-white">Submit Evaluation</button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
