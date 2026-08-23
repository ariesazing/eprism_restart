<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Manuscript Review</p>
            </div>
            <a href="{{ $backUrl }}" class="text-sm font-medium text-red-700">Back</a>
        </div>
    </x-slot>

    @vite(['resources/js/pdf-review.js'])

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('submissions.partials.pdf-viewer', [
                'submission' => $submission,
                'documentViewUrl' => $documentViewUrl,
                'commentsUrl' => $commentsUrl,
                'canCreate' => $canCreate,
                'canEditAll' => $canEditAll,
                'snapshotId' => $snapshotId ?? null,
            ])
        </div>
    </div>
</x-app-layout>
