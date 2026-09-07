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

            <x-modal name="create-account" :show="$errors->createAccount->any() && old('email') !== null" max-width="lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Create Account</h3>
                    <p class="mt-1 text-sm text-slate-500">Accounts created here are active immediately and don't require an email verification step.</p>

                    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid gap-4">
                        @csrf
                        <div>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name" class="w-full rounded-xl border-slate-300 text-sm" required data-rules="required" />
                            <x-input-error :messages="$errors->createAccount->get('name')" field="name" class="mt-1" />
                        </div>
                        <div>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email address" class="w-full rounded-xl border-slate-300 text-sm" required data-rules="required|email" />
                            <x-input-error :messages="$errors->createAccount->get('email')" field="email" class="mt-1" />
                        </div>
                        <div>
                            <x-text-input type="password" name="password" placeholder="Password" class="w-full text-sm" required data-rules="required|password" />
                            <p class="mt-1 text-xs text-slate-500">At least 8 characters, with uppercase, lowercase, a number, and a symbol.</p>
                            <x-input-error :messages="$errors->createAccount->get('password')" field="password" class="mt-1" />
                        </div>
                        <div>
                            <x-text-input type="password" name="password_confirmation" placeholder="Confirm password" class="w-full text-sm" required data-rules="required|matches:password" />
                            <x-input-error :messages="$errors->createAccount->get('password_confirmation')" field="password_confirmation" class="mt-1" />
                        </div>
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

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Notes</th>
                            <th class="px-4 py-3 font-medium">Disabled By</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white align-top">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-slate-900">{{ $user->name }}</div>
                                    <div class="text-slate-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-700">{{ $user->role->label() }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $user->status === \App\Enums\AccountStatus::DISABLED ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $user->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 max-w-xs text-slate-600">{{ $user->status_notes ?: '—' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $user->disabledBy->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-right">
                                    @unless ($user->is(auth()->user()))
                                        <button type="button"
                                            @click="$dispatch('open-modal', 'edit-user-{{ $user->id }}')"
                                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                            Edit
                                        </button>
                                        <button type="button"
                                            @click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')"
                                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                            Delete
                                        </button>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No users match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Edit modals live outside the table so each one's own <form> isn't nested
                 inside any other element's markup. --}}
            @foreach ($users as $user)
                @unless ($user->is(auth()->user()))
                    @php($editBag = 'editUser'.$user->id)
                    <x-modal name="edit-user-{{ $user->id }}" :show="$errors->{$editBag}->any()" max-width="lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Edit {{ $user->name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>

                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 grid gap-4" x-data="{ status: '{{ old('status', $user->status->value) }}' }">
                                @csrf
                                @method('PATCH')

                                <div>
                                    <label class="text-sm font-medium text-slate-700">Name</label>
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" required data-rules="required" />
                                    <x-input-error :messages="$errors->{$editBag}->get('name')" field="name" class="mt-1" />
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-700">New Password</label>
                                    <x-text-input type="password" name="password" class="mt-1 w-full text-sm" data-rules="password" />
                                    <p class="mt-1 text-xs text-slate-500">Leave blank to keep the current password.</p>
                                    <x-input-error :messages="$errors->{$editBag}->get('password')" field="password" class="mt-1" />
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-700">Confirm New Password</label>
                                    <x-text-input type="password" name="password_confirmation" class="mt-1 w-full text-sm" data-rules="matches:password" />
                                    <x-input-error :messages="$errors->{$editBag}->get('password_confirmation')" field="password_confirmation" class="mt-1" />
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-700">Role</label>
                                    <select name="role" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->{$editBag}->get('role')" field="role" class="mt-1" />
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-700">Status</label>
                                    <select name="status" x-model="status" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                        @foreach ($accountStatuses as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->{$editBag}->get('status')" field="status" class="mt-1" />
                                </div>

                                {{-- Only shown (and only required, both client- and server-side —
                                     see UserManagementController::update()'s required_if rule)
                                     once the account is actually being disabled. --}}
                                <div x-show="status === '{{ \App\Enums\AccountStatus::DISABLED->value }}'" x-cloak>
                                    <label class="text-sm font-medium text-slate-700">Notes</label>
                                    <input type="text" name="status_notes" value="{{ old('status_notes', $user->status_notes) }}" placeholder="Reason for disabling" class="mt-1 w-full rounded-xl border-slate-300 text-sm" :required="status === '{{ \App\Enums\AccountStatus::DISABLED->value }}'" />
                                    <x-input-error :messages="$errors->{$editBag}->get('status_notes')" field="status_notes" class="mt-1" />
                                </div>

                                <div class="flex justify-end gap-3">
                                    <button type="button" @click="$dispatch('close-modal', 'edit-user-{{ $user->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                                    <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Save</button>
                                </div>
                            </form>
                        </div>
                    </x-modal>
                @endunless
            @endforeach

            {{-- Delete confirmations live outside the batch form above so their own <form> isn't nested inside it. --}}
            @foreach ($users as $user)
                @unless ($user->is(auth()->user()))
                    <x-modal name="delete-user-{{ $user->id }}" max-width="lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Delete {{ $user->name }}?</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                The account can no longer sign in and is removed from this list. Their submissions,
                                reviews, and activity history stay in the system, and the email address
                                (<span class="font-medium">{{ $user->email }}</span>) is freed up so it can be used to register a new account again.
                            </p>
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-5 flex justify-end gap-3">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="$dispatch('close-modal', 'delete-user-{{ $user->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-500">Delete Account</button>
                            </form>
                        </div>
                    </x-modal>
                @endunless
            @endforeach
        </div>
    </div>
</x-app-layout>
