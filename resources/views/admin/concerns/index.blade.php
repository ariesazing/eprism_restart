<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            User Concerns &amp; Inquiries
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            <x-filter-bar :action="route('admin.concerns.index')" :has-active-filters="(bool) (request('search') || $currentStatus)" :clear-url="route('admin.concerns.index')">
                <input type="search" name="search" value="{{ request('search') }}" aria-label="Search concerns" placeholder="Search concerns or users" class="rounded-xl border-slate-300 text-sm">
                <select name="status" aria-label="Status" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label)<option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>@endforeach
                </select>
            </x-filter-bar>
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-100/90 border-b border-slate-200 text-slate-700 text-xs font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3.5">Subject &amp; Message</th>
                            <th class="px-6 py-3.5">User &amp; Context</th>
                            <th class="px-6 py-3.5">Category</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5">Submitted</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($concerns as $concern)
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-6 py-4 max-w-md">
                                    <div class="font-bold text-slate-900 text-sm">{{ $concern->subject }}</div>
<p class="text-xs text-slate-600">{{ $concern->message }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900 text-xs">{{ ($concern->user?->name ?? 'Deleted user') }}</div>
                                    <div class="text-[11px] text-slate-500">
                                        @if ($concern->submission)
                                            <span class="truncate max-w-xs block font-mono">{{ $concern->submission->reference_code }} &middot; {{ ($concern->submission?->title ?? 'General platform inquiry') }}</span>
                                        @else
                                            <span class="italic text-slate-400">General Platform Inquiry</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium uppercase tracking-wider text-slate-700">
                                        {{ $concern->category }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($concern->status === 'resolved')
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 shadow-2xs">
                                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            Resolved
                                        </span>
                                    @elseif ($concern->status === 'in_progress')
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                            In Progress
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                                            Open
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                                    {{ $concern->created_at->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        type="button"
                                        @click="$dispatch('open-modal', 'respond-concern-{{ $concern->id }}')"
                                        title="Respond to Concern"
                                        aria-label="Respond"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-cherry-300 hover:bg-slate-50 hover:text-cherry-700"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                        <span class="sr-only">Respond</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-xs text-slate-500">
                                    <p class="font-medium text-slate-600">No concerns matching this filter</p>
                                    <p class="mt-1 text-slate-400">All user concerns in this tab are clear.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $concerns->links() }}
            </div>
        </div>
    </div>

    {{-- Response Modals --}}
    @foreach ($concerns as $concern)
        <x-modal name="respond-concern-{{ $concern->id }}" max-width="lg" focusable>
            <form method="POST" action="{{ route('admin.concerns.update', $concern) }}" class="p-6">
                @csrf
                @method('PATCH')
                <h3 class="text-lg font-semibold text-slate-900">Manage Concern #{{ $concern->id }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ ($concern->submission?->title ?? 'General platform inquiry') }} &middot; {{ ($concern->user?->name ?? 'Deleted user') }} ({{ ($concern->user?->email ?? '') }})</p>

                <div class="mt-4 space-y-4 text-xs">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="font-semibold text-slate-900">Subject: {{ $concern->subject }}</div>
                        <p class="mt-1 text-slate-700 whitespace-pre-wrap">{{ $concern->message }}</p>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 uppercase tracking-wider">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-cherry-500 focus:ring-cherry-500">
                            <option value="open" @selected($concern->status === 'open')>Open</option>
                            <option value="in_progress" @selected($concern->status === 'in_progress')>In Progress</option>
                            <option value="resolved" @selected($concern->status === 'resolved')>Resolved</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 uppercase tracking-wider">Admin Response / Note</label>
                        <textarea name="admin_response" rows="4" maxlength="5000" placeholder="Type your response to the researcher here..." class="mt-1 block w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-cherry-500 focus:ring-cherry-500">{{ old('admin_response', $concern->admin_response) }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="$dispatch('close-modal', 'respond-concern-{{ $concern->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cherry-800">Save Response</button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>
