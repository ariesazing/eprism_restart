<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">
                    Workflow Dashboard
                </h2>
                <p class="mt-1 text-sm text-slate-500">Traditional research workflow for researchers, reviewers, and administrators.</p>
            </div>
            <div class="rounded-full bg-cyan-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
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

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">My Submissions</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['my_submissions'] }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Assigned Reviews</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['assigned_reviews'] }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Pending Users</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['pending_users'] }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm text-slate-500">Pending Review Approvals</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['pending_review_approvals'] }}</div>
                </div>
                <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-sm">
                    <div class="text-sm text-slate-300">Approved Research</div>
                    <div class="mt-3 text-3xl font-semibold">{{ $stats['approved_research'] }}</div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1.5fr,1fr]">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Recent Activity</h3>
                        @if (auth()->user()->isResearcher() && auth()->user()->isApproved())
                            <a href="{{ route('submissions.create') }}" class="rounded-full bg-cyan-700 px-4 py-2 text-sm font-medium text-white">New Submission</a>
                        @endif
                    </div>
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Title</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Owner</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($recentSubmissions as $submission)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-800">{{ $submission->title }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $submission->status->label() }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $submission->researcher->name ?? auth()->user()->name }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-slate-500">No recent activity yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Quick Actions</h3>
                    <div class="mt-4 grid gap-3 text-sm">
                        <a href="{{ route('repository.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Browse research repository</a>
                        @if (auth()->user()->isResearcher() && auth()->user()->isApproved())
                            <a href="{{ route('submissions.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Manage my submissions</a>
                        @endif
                        @if (auth()->user()->isReviewer() && auth()->user()->isApproved())
                            <a href="{{ route('reviewer.submissions.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Open reviewer queue</a>
                        @endif
                        @if (auth()->user()->isAdmin() && auth()->user()->isApproved())
                            <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Approve and manage users</a>
                            <a href="{{ route('admin.submissions.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Review workflow queue</a>
                            <a href="{{ route('admin.reports') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-slate-700 hover:bg-slate-50">Open reports</a>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
