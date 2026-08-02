<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">
                    Workflow Dashboard
                </h2>
            </div>
            <div class="rounded-full bg-red-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-red-700">
                {{ auth()->user()->role->label() }}
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

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Quick Actions</h3>
                <div class="mt-4 grid gap-3 text-sm md:grid-cols-2 lg:grid-cols-4">
                    <a href="{{ route('repository.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Browse research repository</a>
                    @if (auth()->user()->isResearcher() && auth()->user()->isApproved())
                        <a href="{{ route('submissions.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">New submission</a>
                        <a href="{{ route('submissions.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Manage my submissions</a>
                    @endif
                    @if (auth()->user()->isReviewer() && auth()->user()->isApproved())
                        <a href="{{ route('reviewer.submissions.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Open reviewer queue</a>
                    @endif
                    @if (auth()->user()->isAdmin() && auth()->user()->isApproved())
                        <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Approve and manage users</a>
                        <a href="{{ route('admin.submissions.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Review workflow queue</a>
                        <a href="{{ route('admin.reports') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Open reports</a>
                        <a href="{{ route('admin.activity.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">View activity log</a>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
