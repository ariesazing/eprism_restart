<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()->with('disabledBy');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'accountStatuses' => AccountStatus::cases(),
            'filters' => [
                'search' => $search ?? '',
                'role' => $role ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Named bag: admin/users/index.blade.php has two forms sharing one page (this one,
        // and the batch role/status update table below it) — the default bag would mix
        // both forms' errors together, so the create-account modal (which keys its
        // reopen-on-error and in-modal error list off $errors->createAccount specifically)
        // could otherwise "reopen" empty for a batch-update failure, or never show a real
        // create-account error inside the modal at all.
        $validated = $request->validateWithBag('createAccount', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->activity->log(
            $request->user(),
            'user.created',
            $user,
            "{$request->user()->name} created a {$validated['role']} account for {$user->name}."
        );

        return back()->with('status', "Account created for {$user->name}.");
    }

    /**
     * Per-user edit modal (admin/users/index.blade.php's Edit button) — name, password
     * (optional), role, and status/notes together, instead of the old batch table's
     * inline-per-row selects. Named error bag ('editUser<id>') so a validation failure
     * reopens *this* user's modal specifically and shows its own errors, not some other
     * row's — every row on the page checks its own (normally-empty) bag, so only the one
     * that actually failed reopens.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $bag = 'editUser'.$user->id;

        $validated = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'status' => ['required', Rule::in(array_map(fn (AccountStatus $status) => $status->value, AccountStatus::cases()))],
            // Only required once the account is actually being disabled — see the
            // status-driven x-show on the notes field in the modal itself.
            'status_notes' => ['required_if:status,'.AccountStatus::DISABLED->value, 'nullable', 'string', 'max:1000'],
        ]);

        if ($request->user()->is($user) && $validated['role'] !== UserRole::ADMIN->value) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator role.'], $bag);
        }

        if ($request->user()->is($user) && $validated['status'] === AccountStatus::DISABLED->value) {
            return back()->withErrors(['status' => 'You cannot disable your own account.'], $bag);
        }

        $wasDisabled = $user->status === AccountStatus::DISABLED;
        $willBeDisabled = $validated['status'] === AccountStatus::DISABLED->value;

        $user->fill([
            'name' => $validated['name'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'status_notes' => $willBeDisabled ? $validated['status_notes'] : null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($willBeDisabled && ! $wasDisabled) {
            $user->disabled_at = now();
            $user->disabled_by = $request->user()->id;
        } elseif (! $willBeDisabled) {
            $user->disabled_at = null;
            $user->disabled_by = null;
        }

        $user->save();

        $this->activity->log(
            $request->user(),
            'user.updated',
            $user,
            "{$request->user()->name} updated the account for {$user->name}."
        );

        return back()->with('status', "Updated {$user->name}.");
    }

    /**
     * One submit for every row on the page. disabled_at/disabled_by only move when a row
     * is genuinely transitioning into or out of the disabled status — not on every save —
     * so re-submitting an already-disabled row without changes doesn't count as "dirty".
     */
    public function batchUpdate(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'users' => ['required', 'array'],
            'users.*.role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'users.*.status' => ['required', Rule::in(array_map(fn (AccountStatus $status) => $status->value, AccountStatus::cases()))],
            'users.*.status_notes' => ['nullable', 'string', 'max:1000'],
        ])['users'];

        $ids = array_map('intval', array_keys($payload));
        $users = User::query()->whereKey($ids)->get()->keyBy('id');
        $changed = 0;

        foreach ($payload as $id => $attributes) {
            $user = $users->get((int) $id);

            if (! $user) {
                continue;
            }

            if ($request->user()->is($user) && $attributes['role'] !== UserRole::ADMIN->value) {
                return back()->withErrors(['users' => 'You cannot remove your own administrator role.']);
            }

            if ($request->user()->is($user) && $attributes['status'] === AccountStatus::DISABLED->value) {
                return back()->withErrors(['users' => 'You cannot disable your own account.']);
            }

            $wasDisabled = $user->status === AccountStatus::DISABLED;
            $willBeDisabled = $attributes['status'] === AccountStatus::DISABLED->value;

            $user->fill([
                'role' => $attributes['role'],
                'status' => $attributes['status'],
                'status_notes' => $attributes['status_notes'] ?? null,
            ]);

            if ($willBeDisabled && ! $wasDisabled) {
                $user->disabled_at = now();
                $user->disabled_by = $request->user()->id;
            } elseif (! $willBeDisabled) {
                $user->disabled_at = null;
                $user->disabled_by = null;
            }

            if ($user->isDirty()) {
                $user->save();
                $changed++;
            }
        }

        if ($changed > 0) {
            $this->activity->log(
                $request->user(),
                'user.batch_updated',
                null,
                "{$request->user()->name} updated {$changed} user account(s)."
            );
        }

        return back()->with('status', $changed > 0 ? "Updated {$changed} user account(s)." : 'No changes to save.');
    }

    /**
     * Soft delete: the row and its data stay in the database (submissions, reviews, and
     * activity-log entries that reference this user's id keep resolving), but the account
     * can no longer sign in and disappears from user management. email itself is
     * overwritten with a synthetic, permanently-unique placeholder — otherwise the real
     * address would sit in the (still unique) email column forever, and anyone (including
     * this same person) trying to register that address again would hit "email already in
     * use" for an account nobody can sign into anymore. deleted-{id}@ is unique by
     * construction (one row per id), so this never collides even across many soft-deleted
     * users. The real address isn't kept anywhere on the row — it's not needed for
     * anything today (no "restore account" feature exists), and the activity log entry
     * below already preserves it as a permanent, human-readable record if it's ever
     * needed, without leaving it sitting on the live table.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['users' => 'You cannot delete your own account.']);
        }

        // Captured before the rewrite below so the audit trail still names the real,
        // meaningful address rather than the synthetic placeholder that replaces it.
        $originalEmail = $user->email;

        $user->update(['email' => "deleted-{$user->id}@eprism.invalid"]);

        $user->delete();

        $this->activity->log(
            $request->user(),
            'user.deleted',
            $user,
            "{$request->user()->name} deleted the account for {$user->name} ({$originalEmail})."
        );

        return back()->with('status', "Deleted the account for {$user->name}.");
    }
}
