<x-app-layout skeleton="table">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Organizational Units</h2>
            <p class="mt-1 text-sm text-slate-500">Schools and offices researchers can select on the submission form. Correct a name or retire a unit no longer accepting new submissions here.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-filter-bar
                :action="route('admin.organizational-units.index')"
                :has-active-filters="(bool) ($filters['search'] || $filters['type'] || $filters['status'])"
                :clear-url="route('admin.organizational-units.index')"
            >
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search name or school ID" class="w-56 rounded-xl border-slate-300 text-sm" />
                <select name="type" class="w-40 rounded-xl border-slate-300 text-sm">
                    <option value="">All types</option>
                    <option value="school" @selected($filters['type'] === 'school')>School</option>
                    <option value="non_school" @selected($filters['type'] === 'non_school')>Non-School</option>
                </select>
                <select name="status" class="w-40 rounded-xl border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
            </x-filter-bar>

            <form method="POST" action="{{ route('admin.organizational-units.batch-update') }}">
                @csrf
                @method('PATCH')

                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">School ID</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($units as $unit)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $unit->school_id ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ str($unit->organizational_unit_type)->headline() }}</td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="units[{{ $unit->id }}][name]" value="{{ $unit->name }}" class="min-w-[16rem] w-full rounded-xl border-slate-300 text-sm" required />
                                    </td>
                                    <td class="px-4 py-3">
                                        <select name="units[{{ $unit->id }}][is_active]" class="rounded-xl border-slate-300 text-sm">
                                            <option value="1" @selected($unit->is_active)>Active</option>
                                            <option value="0" @selected(! $unit->is_active)>Inactive</option>
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">No organizational units match this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($units->isNotEmpty())
                    <div class="mt-4 flex items-center justify-between gap-4">
                        <div>{{ $units->links() }}</div>
                        <button type="submit" class="shrink-0 rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save All Changes</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
