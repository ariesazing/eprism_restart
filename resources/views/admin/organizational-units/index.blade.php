<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Organizational Units</h2>
            <p class="mt-1 text-sm text-slate-500">Schools and offices researchers can select on the submission form. Correct a name or retire a unit no longer accepting new submissions here.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">School ID</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Name &amp; Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($units as $unit)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $unit->school_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ str($unit->organizational_unit_type)->headline() }}</td>
                                <td class="px-4 py-4">
                                    <form method="POST" action="{{ route('admin.organizational-units.update', $unit) }}" class="flex flex-wrap items-center gap-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" value="{{ $unit->name }}" class="min-w-[16rem] flex-1 rounded-xl border-slate-300 text-sm" required />
                                        <select name="is_active" class="rounded-xl border-slate-300 text-sm">
                                            <option value="1" @selected($unit->is_active)>Active</option>
                                            <option value="0" @selected(! $unit->is_active)>Inactive</option>
                                        </select>
                                        <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
