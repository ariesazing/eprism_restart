<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Document Templates</h2>
            <p class="mt-1 text-sm text-slate-500">Edit the HTML templates used to auto-generate each submission type's manuscript.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Submission Templates</h3>
                <p class="mt-1 text-sm text-slate-500">Each template's header and footer are edited together with its body &mdash; open a template below to edit all three.</p>
                <div class="mt-3 grid gap-4">
                    @foreach ($templates as $template)
                        <div class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">{{ $template['label'] }}</h3>
                                @if ($template['record'])
                                    <p class="mt-1 text-sm text-slate-500">
                                        Last updated {{ $template['record']->updated_at->diffForHumans() }}
                                        @if ($template['record']->updater)
                                            by {{ $template['record']->updater->name }}
                                        @endif
                                    </p>
                                @else
                                    <p class="mt-1 text-sm text-amber-600">Not yet configured.</p>
                                @endif
                            </div>

                            <a href="{{ route('admin.document-templates.edit', $template['key']) }}" class="rounded-xl bg-red-700 px-4 py-2 text-center text-sm font-medium text-white hover:bg-red-800">Edit Template</a>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
