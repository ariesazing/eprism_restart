<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
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
        $query = User::query()->with('approver');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('approval_status')) {
            $query->where('approval_status', $status);
        }

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'approvalStatuses' => ApprovalStatus::cases(),
            'filters' => [
                'search' => $search ?? '',
                'role' => $role ?? '',
                'approval_status' => $status ?? '',
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
            'approval_status' => ApprovalStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
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
            'approval_status' => ['required', Rule::in(array_map(fn (ApprovalStatus $status) => $status->value, ApprovalStatus::cases()))],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->user()->is($user) && $validated['role'] !== UserRole::ADMIN->value) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator role.']);
        }

        $user->role = $validated['role'];
        $user->approval_status = $validated['approval_status'];
        $user->approval_notes = $validated['approval_notes'] ?? null;

        if ($validated['approval_status'] === ApprovalStatus::APPROVED->value) {
            $user->approved_at = now();
            $user->approved_by = $request->user()->id;
        } else {
            $user->approved_at = null;
            $user->approved_by = null;
        }

        $user->save();

        $this->activity->log(
            $request->user(),
            'user.updated',
            $user,
            "{$request->user()->name} set {$user->name}'s role to {$validated['role']} and approval status to {$validated['approval_status']}."
        );

        return back()->with('status', 'User account updated.');
    }
}