<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Activity Log</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div>
                    <label class="text-xs font-medium text-slate-700">Action</label>
                    <select name="action" class="mt-1 rounded-xl border-slate-300 text-sm">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Date</label>
                    <input type="date" name="date" value="{{ request('date') }}" class="mt-1 rounded-xl border-slate-300 text-sm" />
                </div>
                <button type="submit" class="rounded-full bg-red-700 px-4 py-2 text-sm font-medium text-white">Filter</button>
                @if (request('action') || request('date'))
                    <a href="{{ route('admin.activity.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">Clear</a>
                @endif
            </form>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 font-medium">Who</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                            <th class="px-4 py-3 font-medium">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $log->causer->name ?? 'System' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $log->action }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">No activity recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
