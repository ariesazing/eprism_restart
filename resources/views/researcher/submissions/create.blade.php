<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Create Submission</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" class="mt-2 w-full rounded-xl border-slate-300" required />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Course / Department</label>
                        <input type="text" name="course" value="{{ old('course') }}" class="mt-2 w-full rounded-xl border-slate-300" required />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Authors</label>
                    <textarea name="authors" rows="2" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('authors') }}</textarea>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Abstract</label>
                    <textarea name="abstract" rows="6" class="mt-2 w-full rounded-xl border-slate-300" required>{{ old('abstract') }}</textarea>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Keywords</label>
                    <input type="text" name="keywords" value="{{ old('keywords') }}" class="mt-2 w-full rounded-xl border-slate-300" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Supporting Documents</label>
                    <input type="file" name="documents[]" multiple class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
                    <p class="mt-2 text-xs text-slate-500">Accepted formats: PDF, DOC, DOCX. Up to 10MB each.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="action" value="draft" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700">Save Draft</button>
                    <button type="submit" name="action" value="submit" class="rounded-full bg-cyan-700 px-5 py-2.5 text-sm font-medium text-white">Submit for Review</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>