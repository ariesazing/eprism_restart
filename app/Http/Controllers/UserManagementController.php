<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('approver')->orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'approvalStatuses' => ApprovalStatus::cases(),
        ]);
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

        return back()->with('status', 'User account updated.');
    }
}