<x-app-layout skeleton="table">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">User Management</h2>
            <button type="button" @click="$dispatch('open-modal', 'create-account')" class="inline-flex items-center gap-2 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white hover:bg-cherry-800">
                <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                Create Account
            </button>
        </div>
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

            <x-modal name="create-account" :show="$errors->any() && old('email') !== null" max-width="lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Create Account</h3>
                    <p class="mt-1 text-sm text-slate-500">Accounts created here are active immediately and don't require an email verification step.</p>
                    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid gap-4">
                        @csrf
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name" class="rounded-xl border-slate-300 text-sm" required />
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email address" class="rounded-xl border-slate-300 text-sm" required />
                        <x-text-input type="password" name="password" placeholder="Password" class="w-full text-sm" required />
                        <x-text-input type="password" name="password_confirmation" placeholder="Confirm password" class="w-full text-sm" required />
                        <select name="role" class="rounded-xl border-slate-300 text-sm" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="$dispatch('close-modal', 'create-account')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Create Account</button>
                        </div>
                    </form>
                </div>
            </x-modal>

            <x-filter-bar
                :action="route('admin.users.index')"
                :has-active-filters="(bool) ($filters['search'] || $filters['role'] || $filters['status'])"
                :clear-url="route('admin.users.index')"
                class="mb-6 block"
            >
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search name or email" class="w-52 rounded-xl border-slate-300 text-sm" />
                <select name="role" class="w-40 rounded-xl border-slate-300 text-sm">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <select name="status" class="w-40 rounded-xl border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach ($accountStatuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            <form method="POST" action="{{ route('admin.users.batch-update') }}">
                @csrf
                @method('PATCH')

                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">User</th>
                                <th class="px-4 py-3 font-medium">Role</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Notes</th>
                                <th class="px-4 py-3 font-medium">Disabled By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white align-top">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-slate-900">{{ $user->name }}</div>
                                        <div class="text-slate-500">{{ $user->email }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <select name="users[{{ $user->id }}][role]" class="rounded-xl border-slate-300 text-sm">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-4">
                                        <select name="users[{{ $user->id }}][status]" class="rounded-xl border-slate-300 text-sm">
                                            @foreach ($accountStatuses as $status)
                                                <option value="{{ $status->value }}" @selected($user->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input type="text" name="users[{{ $user->id }}][status_notes]" value="{{ $user->status_notes }}" placeholder="Notes (e.g. reason for disabling)" class="min-w-[14rem] w-full rounded-xl border-slate-300 text-sm" />
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">{{ $user->disabledBy->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">No users match this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->isNotEmpty())
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="rounded-xl bg-cherry-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save All Changes</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
