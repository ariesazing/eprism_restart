<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">User Management</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Approval</th>
                            <th class="px-4 py-3 font-medium">Approved By</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white align-top">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-slate-900">{{ $user->name }}</div>
                                    <div class="text-slate-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-600">{{ $user->role->label() }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $user->approval_status->label() }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $user->approver->name ?? 'Not approved yet' }}</td>
                                <td class="px-4 py-4">
                                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-3 lg:grid-cols-4">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="rounded-xl border-slate-300 text-sm">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <select name="approval_status" class="rounded-xl border-slate-300 text-sm">
                                            @foreach ($approvalStatuses as $status)
                                                <option value="{{ $status->value }}" @selected($user->approval_status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="approval_notes" value="{{ $user->approval_notes }}" placeholder="Approval notes" class="rounded-xl border-slate-300 text-sm" />
                                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white">Save</button>
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