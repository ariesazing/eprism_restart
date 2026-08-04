<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">User Management</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Create Account</h3>
                <p class="mt-1 text-sm text-slate-500">Accounts created here are approved immediately and don't require an email verification step.</p>
                <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid gap-4 md:grid-cols-5">
                    @csrf
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name" class="rounded-xl border-slate-300 text-sm" required />
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Email address" class="rounded-xl border-slate-300 text-sm" required />
                    <input type="password" name="password" placeholder="Password" class="rounded-xl border-slate-300 text-sm" required />
                    <input type="password" name="password_confirmation" placeholder="Confirm password" class="rounded-xl border-slate-300 text-sm" required />
                    <select name="role" class="rounded-xl border-slate-300 text-sm" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-medium text-white md:col-span-5 md:w-fit">Create Account</button>
                </form>
            </div>

            <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-4">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search name or email" class="rounded-xl border-slate-300 text-sm" />
                <select name="role" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <select name="approval_status" class="rounded-xl border-slate-300 text-sm">
                    <option value="">All approval statuses</option>
                    @foreach ($approvalStatuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['approval_status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
                    @if ($filters['search'] || $filters['role'] || $filters['approval_status'])
                        <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </form>

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
                        @forelse ($users as $user)
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
                                        <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-medium text-white">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No users match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
