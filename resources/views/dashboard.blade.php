<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                @if ($role === 'researcher')
                    <h2 class="text-xl font-semibold leading-tight text-slate-800">Research Dashboard</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Monitor your research submissions, requirements, and reviewer feedback.</p>
                @else
                    <h2 class="text-xl font-semibold leading-tight text-slate-800">Workflow Dashboard</h2>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <x-dropdown align="right" width="w-72">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 4 14h6l-1 7 9-11h-6l1-7z"></path></svg>
                            Quick Actions
                            <svg class="h-3.5 w-3.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="grid gap-1 p-2 text-sm">
                            <a href="{{ route('repository.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Browse research repository</a>
                            @if (auth()->user()->isResearcher() && auth()->user()->isApproved())
                                <a href="{{ route('submissions.create') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">New submission</a>
                                <a href="{{ route('submissions.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Manage my submissions</a>
                            @endif
                            @if (auth()->user()->isReviewer() && auth()->user()->isApproved())
                                <a href="{{ route('reviewer.submissions.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Open reviewer queue</a>
                            @endif
                            @if (auth()->user()->isAdmin() && auth()->user()->isApproved())
                                <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Approve and manage users</a>
                                <a href="{{ route('admin.submissions.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Review workflow queue</a>
                                <a href="{{ route('admin.reports') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Open reports</a>
                                <a href="{{ route('admin.activity.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">View activity log</a>
                            @endif
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
            @if (! auth()->user()->isApproved())
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-amber-900">Approval pending</h3>
                    <p class="mt-2 text-sm text-amber-700">Your account is waiting for administrator approval. You can update your profile now, but workflow modules unlock only after approval.</p>
                </section>
            @endif

            @if ($role === 'admin')
                @include('dashboard.admin', ['data' => $data])
            @elseif ($role === 'reviewer')
                @include('dashboard.reviewer', ['data' => $data])
            @elseif ($role === 'researcher')
                @include('dashboard.researcher', ['data' => $data])
            @endif
        </div>
    </div>
</x-app-layout>
