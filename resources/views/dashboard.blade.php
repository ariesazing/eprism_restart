<x-app-layout skeleton="dashboard">
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
                            @if (auth()->user()->isResearcher())
                                <a href="{{ route('submissions.create') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">New submission</a>
                                <a href="{{ route('submissions.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Manage my submissions</a>
                            @endif
                            @if (auth()->user()->isReviewer())
                                <a href="{{ route('reviewer.submissions.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Open reviewer queue</a>
                            @endif
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Manage users</a>
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
            @if ($role === 'researcher')
                <div id="guest-draft-claiming-notice" class="hidden rounded-2xl border border-cherry-200 bg-cherry-50 p-4 text-sm text-cherry-700">
                    Saving the research draft you started before registering&hellip;
                </div>
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

    @if ($role === 'researcher')
        <script>
            (function () {
                // Claims a guest draft staged in localStorage before registration (see
                // guest-draft.js) by POSTing it to the same authenticated endpoint any
                // logged-in researcher's "New submission" form already uses — a guest
                // was never able to reach that endpoint directly, only stage data for it.
                const STORAGE_KEY = 'eprism_guest_draft';
                const EXPIRY_MS = 24 * 60 * 60 * 1000;

                let raw;
                try {
                    raw = localStorage.getItem(STORAGE_KEY);
                } catch (e) {
                    return;
                }
                if (! raw) return;

                let draft;
                try {
                    draft = JSON.parse(raw);
                } catch (e) {
                    localStorage.removeItem(STORAGE_KEY);
                    return;
                }

                if (! draft.savedAt || Date.now() - draft.savedAt > EXPIRY_MS) {
                    localStorage.removeItem(STORAGE_KEY);
                    return;
                }

                const notice = document.getElementById('guest-draft-claiming-notice');
                notice?.classList.remove('hidden');

                const formData = new FormData();
                formData.append('title', draft.title || '');
                formData.append('research_type', draft.research_type || 'basic');
                formData.append('classification', draft.classification || 'proposal');
                formData.append('organizational_unit', draft.organizational_unit || '');
                formData.append('school_id', draft.school_id || '');
                formData.append('proponents[0][last_name]', draft.proponent?.last_name || '');
                formData.append('proponents[0][first_name]', draft.proponent?.first_name || '');
                formData.append('proponents[0][middle_initial]', draft.proponent?.middle_initial || '');
                formData.append('proponents[0][position]', draft.proponent?.position || '');

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                fetch('{{ route('submissions.store') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                }).then(function (response) {
                    if (response.ok) {
                        localStorage.removeItem(STORAGE_KEY);
                        window.location.href = response.url;
                        return;
                    }

                    // Left in localStorage on failure (e.g. a transient error) so the
                    // next dashboard visit tries again rather than losing the draft.
                    notice?.classList.add('hidden');
                }).catch(function () {
                    notice?.classList.add('hidden');
                });
            })();
        </script>
    @endif
</x-app-layout>
