<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            Researcher Concerns &amp; Inquiries
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Tabs --}}
            <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
                <a href="{{ route('admin.concerns.index') }}" class="rounded-xl px-4 py-2 text-xs font-semibold {{ ! $currentStatus ? 'bg-cherry-700 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' }}">
                    All ({{ $counts['all'] }})
                </a>
                <a href="{{ route('admin.concerns.index', ['status' => 'open']) }}" class="rounded-xl px-4 py-2 text-xs font-semibold {{ $currentStatus === 'open' ? 'bg-cherry-700 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' }}">
                    Open ({{ $counts['open'] }})
                </a>
                <a href="{{ route('admin.concerns.index', ['status' => 'in_progress']) }}" class="rounded-xl px-4 py-2 text-xs font-semibold {{ $currentStatus === 'in_progress' ? 'bg-cherry-700 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' }}">
                    In Progress ({{ $counts['in_progress'] }})
                </a>
                <a href="{{ route('admin.concerns.index', ['status' => 'resolved']) }}" class="rounded-xl px-4 py-2 text-xs font-semibold {{ $currentStatus === 'resolved' ? 'bg-cherry-700 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' }}">
                    Resolved ({{ $counts['resolved'] }})
                </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="px-6 py-3.5">Submission &amp; Researcher</th>
                            <th class="px-6 py-3.5">Category</th>
                            <th class="px-6 py-3.5">Subject &amp; Message</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5">Submitted</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($concerns as $concern)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900">{{ $concern->submission->title }}</div>
                                    <div class="text-xs text-slate-500">{{ $concern->submission->reference_code }} &middot; {{ $concern->user->name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase tracking-wider text-slate-600">{{ $concern->category }}</span>
                                </td>
                                <td class="px-6 py-4 max-w-md">
                                    <div class="font-semibold text-slate-900 text-xs">{{ $concern->subject }}</div>
                                    <div class="mt-0.5 text-xs text-slate-600 line-clamp-2">{{ $concern->message }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($concern->status === 'resolved')
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Resolved</span>
                                    @elseif ($concern->status === 'in_progress')
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">In Progress</span>
                                    @else
                                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Open</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                                    {{ $concern->created_at->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button type="button" @click="$dispatch('open-modal', 'respond-concern-{{ $concern->id }}')" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:border-cherry-300 hover:text-cherry-700">
                                        Respond
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-xs text-slate-500">
                                    No concerns matching the filter.
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
                <p class="mt-1 text-xs text-slate-500">{{ $concern->submission->title }} &middot; {{ $concern->user->name }} ({{ $concern->user->email }})</p>

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
