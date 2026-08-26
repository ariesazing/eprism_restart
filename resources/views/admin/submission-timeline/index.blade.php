<x-app-layout skeleton="form">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Submission Timeline</h2>
            <p class="mt-1 text-sm text-slate-500">Open or close new proposal and completed-research submissions, and optionally schedule a date range they're accepted within.</p>
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

            <form method="POST" action="{{ route('admin.submission-timeline.update') }}" class="grid gap-6">
                @csrf
                @method('PATCH')

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
                            <select name="windows[{{ $classification }}][is_open]" class="w-32 rounded-xl border-slate-300 text-sm">
                                <option value="1" @selected($window->is_open)>Open</option>
                                <option value="0" @selected(! $window->is_open)>Closed</option>
                            </select>
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
