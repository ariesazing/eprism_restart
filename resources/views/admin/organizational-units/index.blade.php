<x-app-layout skeleton="table">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">Organizational Units</h2>
                <p class="mt-1 text-sm text-slate-500">Schools and offices researchers can select on the submission form. Correct a name or retire a unit no longer accepting new submissions here.</p>
            </div>
            <button type="button" @click="$dispatch('open-modal', 'create-organizational-unit')" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">
                <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                Add Organizational Unit
            </button>
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

            <x-modal name="create-organizational-unit" :show="$errors->any() && old('name') !== null" max-width="lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Add Organizational Unit</h3>
                    <p class="mt-1 text-sm text-slate-500">New units are active immediately and appear right away on the submission form's School/Station list.</p>
                    <form method="POST" action="{{ route('admin.organizational-units.store') }}" class="mt-4 grid gap-4">
                        @csrf
                        <div>
                            <label class="text-xs font-medium text-slate-700">Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" required />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-700">School ID (optional)</label>
                            <input type="text" name="school_id" value="{{ old('school_id') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                            <p class="mt-1 text-xs text-slate-400">Leave blank for non-school offices.</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-700">Type</label>
                            <select name="organizational_unit_type" class="mt-1 w-full rounded-xl border-slate-300 text-sm" required>
                                <option value="school" @selected(old('organizational_unit_type') === 'school')>School</option>
                                <option value="non_school" @selected(old('organizational_unit_type') === 'non_school')>Non-School</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', true)) />
                            Active (accepting submissions immediately)
                        </label>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="$dispatch('close-modal', 'create-organizational-unit')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Add Unit</button>
                        </div>
                    </form>
                </div>
            </x-modal>

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
