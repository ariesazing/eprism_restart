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

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'status' => ['required', Rule::in(array_map(fn (AccountStatus $status) => $status->value, AccountStatus::cases()))],
            'status_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->user()->is($user) && $validated['role'] !== UserRole::ADMIN->value) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator role.']);
        }

        if ($request->user()->is($user) && $validated['status'] === AccountStatus::DISABLED->value) {
            return back()->withErrors(['status' => 'You cannot disable your own account.']);
        }

        $user->role = $validated['role'];
        $user->status = $validated['status'];
        $user->status_notes = $validated['status_notes'] ?? null;

        if ($validated['status'] === AccountStatus::DISABLED->value) {
            $user->disabled_at = now();
            $user->disabled_by = $request->user()->id;
        } else {
            $user->disabled_at = null;
            $user->disabled_by = null;
        }

        $user->save();

        $this->activity->log(
            $request->user(),
            'user.updated',
            $user,
            "{$request->user()->name} set {$user->name}'s role to {$validated['role']} and account status to {$validated['status']}."
        );

        return back()->with('status', 'User account updated.');
    }
}
