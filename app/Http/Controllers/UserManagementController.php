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
        $validated = $request->validate([
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
}
