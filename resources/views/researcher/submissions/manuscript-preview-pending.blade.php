<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $submission->title }}</h2>
            </div>
            <a href="{{ $backUrl }}" class="text-sm font-medium text-cherry-700">Back to submission</a>
        </div>
    </x-slot>
    @vite(['resources/js/manuscript.js'])
    <div class="mx-auto max-w-2xl px-6 py-16 text-center" data-manuscript-preview-pending data-status-url="{{ $statusUrl }}">
        <p role="status" data-manuscript-message class="text-sm text-slate-600">
            @if ($error)
                {{ $error }}
            @else
                Preparing your manuscript preview from the current saved document…
            @endif
        </p>
        <p class="mt-2 text-xs text-slate-400">This page updates automatically once it's ready.</p>
    </div>
</x-app-layout>
