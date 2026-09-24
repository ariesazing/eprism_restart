<x-app-layout skeleton="table">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">Office / School Units</h2>
                <p class="mt-1 text-sm text-slate-500">Schools and offices researchers can select on the submission form. Correct a name, retire a unit no longer accepting new submissions, or delete one (deleted units can be restored from the Deleted filter).</p>
            </div>
            <button type="button" @click="$dispatch('open-modal', 'create-organizational-unit')" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">
                <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                Add Office / School Unit
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
                    <h3 class="text-lg font-semibold text-slate-900">Add Office / School Unit</h3>
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
                            <button type="button" @click="$dispatch('close-modal', 'create-organizational-unit')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Cancel" />Cancel</button>
                            <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800"><x-action-icon action="Add Unit" />Add Unit</button>
                        </div>
                    </form>
                </div>
            </x-modal>

            <x-filter-bar
                :action="route('admin.organizational-units.index')"
                :has-active-filters="(bool) (request('sort', 'newest') !== 'newest' || $filters['search'] || $filters['type'] || $filters['status'])"
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
                    <option value="deleted" @selected($filters['status'] === 'deleted')>Deleted</option>
                </select>
                <label class="text-xs font-medium text-slate-700">Created date
                    <select name="sort" class="mt-1 block rounded-xl border-slate-300 text-sm">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                    </select>
                </label>
            </x-filter-bar>

            @if ($filters['status'] === 'deleted')
                <div class="app-card app-table-scroll bg-white">
                    <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">School ID</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Deleted</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($units as $unit)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $unit->school_id ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ str($unit->organizational_unit_type)->headline() }}</td>
                                    <td class="px-4 py-3 text-slate-800">{{ $unit->name }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $unit->deleted_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.organizational-units.restore', $unit->id) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Restore" />Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">No deleted units.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $units->links() }}</div>
            @else
                <div class="app-card app-table-scroll bg-white">
                    <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3">School ID</th><th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Name</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($units as $unit)
                                <tr>
                                    <td class="px-4 py-3">{{ $unit->school_id ?? '?' }}</td>
                                    <td class="px-4 py-3">{{ str($unit->organizational_unit_type)->headline() }}</td>
                                    <td class="px-4 py-3">{{ $unit->name }}</td>
                                    <td class="px-4 py-3">{{ $unit->is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <button type="button" @click="$dispatch('open-modal', 'edit-unit-{{ $unit->id }}')" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100"><x-action-icon action="Edit" />Edit</button>
                                        <button type="button" @click="$dispatch('open-modal', 'delete-unit-{{ $unit->id }}')" class="rounded-lg px-3 py-2 text-rose-700 hover:bg-rose-50"><x-action-icon action="Delete" />Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No organizational units match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $units->links() }}
                @foreach ($units as $unit)
                    <x-modal name="edit-unit-{{ $unit->id }}" :show="$errors->getBag('editUnit'.$unit->id)->any()" max-width="lg">
                        <form method="POST" action="{{ route('admin.organizational-units.update', $unit) }}" class="grid gap-4 p-6">
                            @csrf
                            @method('PATCH')
                            <h3 class="text-lg font-semibold text-slate-900">Edit {{ $unit->name }}</h3>
                            <x-input-error :messages="$errors->getBag('editUnit'.$unit->id)->all()" />
                            <label class="text-sm text-slate-700">Name
                                <input name="name" value="{{ $errors->getBag('editUnit'.$unit->id)->any() ? old('name') : $unit->name }}" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300" />
                            </label>
                            <label class="text-sm text-slate-700">School ID
                                <input name="school_id" value="{{ $errors->getBag('editUnit'.$unit->id)->any() ? old('school_id') : $unit->school_id }}" maxlength="255" class="mt-1 w-full rounded-xl border-slate-300" />
                            </label>
                            <label class="text-sm text-slate-700">Status
                                <select name="is_active" class="mt-1 w-full rounded-xl border-slate-300">
                                    <option value="1" @selected($errors->getBag('editUnit'.$unit->id)->any() ? old('is_active') == 1 : $unit->is_active)>Active</option>
                                    <option value="0" @selected($errors->getBag('editUnit'.$unit->id)->any() ? old('is_active') == 0 : ! $unit->is_active)>Inactive</option>
                                </select>
                            </label>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="$dispatch('close-modal', 'edit-unit-{{ $unit->id }}')" class="rounded-xl border border-slate-300 px-4 py-2"><x-action-icon action="Cancel" />Cancel</button>
                                <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-white"><x-action-icon action="Save Changes" />Save Changes</button>
                            </div>
                        </form>
                    </x-modal>
                @endforeach

                {{-- Each listing has its own edit and delete confirmation. --}}
                @foreach ($units as $unit)
                    <x-modal name="delete-unit-{{ $unit->id }}" max-width="lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Delete {{ $unit->name }}?</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                It is removed from this list and from the School/Station choices on the submission form. Existing submissions
                                keep the name they were filed under, and you can bring the unit back any time from the <span class="font-medium">Deleted</span> filter.
                            </p>
                            <form method="POST" action="{{ route('admin.organizational-units.destroy', $unit) }}" class="mt-5 flex justify-end gap-3">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="$dispatch('close-modal', 'delete-unit-{{ $unit->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Cancel" />Cancel</button>
                                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-500"><x-action-icon action="Delete Unit" />Delete Unit</button>
                            </form>
                        </div>
                    </x-modal>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
