<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Researcher: {{ $submission->researcher->name }} · {{ $submission->course }}</p>
            </div>
            <a href="{{ route('reviewer.submissions.index') }}" class="text-sm font-medium text-cyan-700">Back to queue</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8 lg:grid-cols-[1fr,0.9fr]">
            <section class="grid gap-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Research Information</h3>
                    <p class="mt-4 text-sm leading-7 text-slate-600">{{ $submission->abstract }}</p>
                    <div class="mt-4 grid gap-3 text-sm text-slate-600">
                        <div><span class="font-medium text-slate-900">Authors:</span> {{ $submission->authors }}</div>
                        <div><span class="font-medium text-slate-900">Keywords:</span> {{ $submission->keywords ?: 'N/A' }}</div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Documents</h3>
                    <div class="mt-4 grid gap-3">
                        @foreach ($submission->documents as $document)
                            <a href="{{ route('reviewer.submissions.documents.download', [$submission, $document]) }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">{{ $document->document_type }} · {{ $document->original_name }}</a>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Rubric Scoring</h3>
                <form method="POST" action="{{ route('reviewer.submissions.review', $submission) }}" class="mt-4 grid gap-5">
                    @csrf
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
                            @foreach (['approve' => 'Approve', 'minor_revision' => 'Minor Revision', 'major_revision' => 'Major Revision', 'reject' => 'Reject'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('recommendation', $existingReview->recommendation ?? 'minor_revision') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Comments</label>
                        <textarea name="comments" rows="8" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('comments', $existingReview->comments ?? '') }}</textarea>
                    </div>
                    <button type="submit" class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-medium text-white">Submit Evaluation</button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>