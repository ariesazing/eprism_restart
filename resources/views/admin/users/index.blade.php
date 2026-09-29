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

            <x-modal name="create-account" :reset-on-close="true" :show="$errors->createAccount->any() && old('email') !== null" max-width="lg">
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
                            <option value="">Choose a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="$dispatch('close-modal', 'create-account')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Cancel" />Cancel</button>
                            <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800"><x-action-icon action="Create Account" />Create Account</button>
                        </div>
                    </form>
                </div>
            </x-modal>

            {{-- Search & Clear Filters --}}
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-1 items-center gap-2 max-w-md">
                    <div class="relative flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Search name or email..."
                            class="w-full rounded-xl border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-cherry-300 focus:ring-cherry-200"
                        />
                    </div>
                    @if ($filters['role']) <input type="hidden" name="role" value="{{ $filters['role'] }}"> @endif
                    @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
                    @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                    <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cherry-800 transition">
                        Search
                    </button>
                    @if ($filters['search'] || $filters['role'] || $filters['status'] || request('sort', 'newest') !== 'newest')
                        <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition" title="Clear all filters">
                            Clear
                        </a>
                    @endif
                </form>

                <div class="text-xs text-slate-500 font-medium">
                    Showing <span class="font-semibold text-slate-800">{{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }}</span> of <span class="font-semibold text-slate-800">{{ $users->total() }}</span> accounts
                </div>
            </div>

            <div class="app-card app-table-scroll overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="research-table min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-100/90 border-b border-slate-200 text-slate-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">User Name</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Email Address</th>
                            <x-table-filter-header
                                label="Role"
                                filter-name="role"
                                :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])"
                                :current-value="$filters['role']"
                            />
                            <x-table-filter-header
                                label="Status"
                                filter-name="status"
                                :options="collect($accountStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])"
                                :current-value="$filters['status']"
                            />
                            <x-table-filter-header
                                label="Created"
                                sort-param="sort" sort-asc="oldest" sort-desc="newest"
                                :current-sort="request('sort', 'newest') === 'oldest' ? 'asc' : 'desc'"
                            />
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white align-top">
                        @forelse ($users as $user)
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-4 py-3.5">
                                    <span class="font-bold text-slate-900">{{ $user->name }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $user->email }}</td>
                                <td class="px-4 py-3.5 text-slate-700">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($user->status === \App\Enums\AccountStatus::DISABLED)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800 shadow-2xs">
                                            <svg class="h-3.5 w-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            {{ $user->status->label() }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                            {{ $user->status->label() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                    {{ $user->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button type="button"
                                            @click="$dispatch('open-modal', 'view-user-{{ $user->id }}')"
                                            title="View Account Details"
                                            aria-label="View"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-cherry-300 hover:bg-slate-50 hover:text-cherry-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span class="sr-only">View</span>
                                        </button>
                                        @unless ($user->is(auth()->user()))
                                            <button type="button"
                                                @click="$dispatch('open-modal', 'edit-user-{{ $user->id }}')"
                                                title="Edit Account"
                                                aria-label="Edit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-cherry-300 hover:bg-slate-50 hover:text-cherry-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                <span class="sr-only">Edit</span>
                                            </button>
                                            <button type="button"
                                                @click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')"
                                                title="Delete Account"
                                                aria-label="Delete"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-rose-600 shadow-sm transition hover:border-rose-300 hover:bg-rose-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                <span class="sr-only">Delete</span>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                                    <p class="font-medium text-slate-600">No users match this filter</p>
                                    <p class="mt-1 text-xs text-slate-400">Try adjusting your filters or search terms.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>

            {{-- View modals: full account information at a glance, including the fields
                 dropped from the table (status notes, who disabled the account). --}}
            @foreach ($users as $user)
                <x-modal name="view-user-{{ $user->id }}" max-width="lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $user->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Role</dt>
                                <dd class="mt-1 text-slate-800">{{ $user->role->label() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Status</dt>
                                <dd class="mt-1">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $user->status === \App\Enums\AccountStatus::DISABLED ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $user->status->label() }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Email Verified</dt>
                                <dd class="mt-1 text-slate-800">{{ $user->email_verified_at ? $user->email_verified_at->format('M j, Y g:i A') : 'Not verified' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Member Since</dt>
                                <dd class="mt-1 text-slate-800">{{ $user->created_at->format('M j, Y') }}</dd>
                            </div>
                            @if ($user->status === \App\Enums\AccountStatus::DISABLED)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Disabled By</dt>
                                    <dd class="mt-1 text-slate-800">{{ $user->disabledBy->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Disabled At</dt>
                                    <dd class="mt-1 text-slate-800">{{ $user->disabled_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Notes</dt>
                                    <dd class="mt-1 text-slate-800">{{ $user->status_notes ?: '—' }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="mt-6 flex justify-end">
                            <button type="button" @click="$dispatch('close-modal', 'view-user-{{ $user->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Close" />Close</button>
                        </div>
                    </div>
                </x-modal>
            @endforeach

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
                                    <p class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $user->role->label() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">A role can't be changed after an account is created — submissions and reviews are tied to it. Delete and recreate the account instead.</p>
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
                                    <button type="button" @click="$dispatch('close-modal', 'edit-user-{{ $user->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Cancel" />Cancel</button>
                                    <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800"><x-action-icon action="Save" />Save</button>
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
                                <button type="button" @click="$dispatch('close-modal', 'delete-user-{{ $user->id }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-action-icon action="Cancel" />Cancel</button>
                                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-500"><x-action-icon action="Delete Account" />Delete Account</button>
                            </form>
                        </div>
                    </x-modal>
                @endunless
            @endforeach
        </div>
    </div>
</x-app-layout>
