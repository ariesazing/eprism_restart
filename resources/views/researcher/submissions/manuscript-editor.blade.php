<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $submission->title }}</h2>
                <p class="text-sm text-slate-500">{{ $template->label }}</p>
            </div>
            <a href="{{ route('submissions.show', $submission) }}" data-manuscript-close class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save and return</a>
        </div>
    </x-slot>
    @vite(['resources/js/manuscript.js'])
    <div
        data-manuscript-editor
        data-status-url="{{ route('submissions.manuscript.status', $submission) }}"
        data-config-url="{{ route('submissions.manuscript.config', $submission) }}"
        data-return-url="{{ route('submissions.show', $submission) }}"
        data-office-url="{{ config('services.onlyoffice.url') }}"
        class="mx-auto max-w-full px-4 sm:px-6 lg:px-8"
    >
        <p data-manuscript-message role="status" class="mb-3 text-sm text-slate-600">Loading the manuscript editor…</p>
        <div id="complete-manuscript-editor" class="w-full overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"></div>
    </div>
</x-app-layout>
