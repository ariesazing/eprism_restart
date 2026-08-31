<x-app-layout skeleton="form">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Submission Timeline</h2>
            <p class="mt-1 text-sm text-slate-500">Only one of Proposal or Completed Research can accept new submissions at a time — opening one automatically closes the other. Each still keeps its own optional date range.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-4xl gap-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.submission-timeline.update') }}" enctype="multipart/form-data" class="grid gap-6">
                @csrf
                @method('PATCH')

                @php
                    $openClassification = old('open_classification') ?? collect($windows)->first(fn ($w) => $w->is_open)?->classification ?? 'none';
                @endphp

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Currently Accepting</h3>
                    <p class="mt-1 text-sm text-slate-500">Choosing a classification here automatically closes the other one.</p>
                    <select name="open_classification" class="mt-3 w-full rounded-xl border-slate-300 text-sm sm:w-64">
                        <option value="proposal" @selected($openClassification === 'proposal')>Proposal Research</option>
                        <option value="completed" @selected($openClassification === 'completed')>Completed Research</option>
                        <option value="none" @selected($openClassification === 'none')>Neither (fully closed)</option>
                    </select>
                </div>

                @foreach ($windows as $classification => $window)
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $classification === 'proposal' ? 'Proposal Research' : 'Completed Research' }}</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    @if ($classification === 'proposal')
                                        Governs whether researchers can create and submit new proposal drafts.
                                    @else
                                        Governs whether a promoted (proposal-approved) draft can be submitted as completed research.
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $window->is_open ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $window->is_open ? 'Open' : 'Closed' }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-slate-700">Opens</label>
                                <input type="date" name="windows[{{ $classification }}][opens_at]" value="{{ optional($window->opens_at)->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-700">Closes</label>
                                <input type="date" name="windows[{{ $classification }}][closes_at]" value="{{ optional($window->closes_at)->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">Leave both blank to accept submissions indefinitely while Open. If set, submissions are only accepted through the end of the Closes date.</p>

                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <label class="text-xs font-medium text-slate-700">Memorandum (optional PDF)</label>
                            <p class="mt-1 text-xs text-slate-400">Shown to guests on the public welcome page alongside this classification's timeline.</p>

                            @if ($window->memorandum_path)
                                <div class="mt-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3 text-sm">
                                    <a href="{{ route('submission-timeline.memorandum', $classification) }}" target="_blank" class="font-medium text-cherry-700 hover:underline">{{ $window->memorandum_original_name }}</a>
                                    <label class="ml-auto flex items-center gap-1.5 text-xs text-slate-500">
                                        <input type="checkbox" name="windows[{{ $classification }}][remove_memorandum]" value="1" class="rounded border-slate-300" />
                                        Remove
                                    </label>
                                </div>
                            @endif

                            <input type="file" name="windows[{{ $classification }}][memorandum]" accept="application/pdf" class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200" />
                        </div>

                        @if ($window->updater)
                            <p class="mt-3 text-xs text-slate-400">Last updated {{ $window->updated_at->diffForHumans() }} by {{ $window->updater->name }}.</p>
                        @endif
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
