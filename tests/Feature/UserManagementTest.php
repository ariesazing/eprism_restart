<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_soft_delete_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect();

        $this->assertSoftDeleted($target);
    }

    /**
     * Regression test: email used to stay put (unchanged) on a soft-deleted user, and
     * since it's a unique column, that permanently blocked re-registering that same
     * address — for the deleted person or anyone else. destroy() now overwrites `email`
     * with a synthetic placeholder to free the real address for reuse (the real address
     * isn't kept anywhere on the row — see destroy()'s docblock for why — but the
     * activity log entry it also writes does preserve it as a permanent record).
     */
    public function test_soft_deleting_a_user_frees_their_email_for_reuse(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'reusable@example.com']);
        $targetId = $target->id;

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect();

        $deleted = User::withTrashed()->findOrFail($targetId);
        $this->assertNotSame('reusable@example.com', $deleted->email);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.deleted',
            'description' => "{$admin->name} deleted the account for {$target->name} (reusable@example.com).",
        ]);

        // The address itself must be genuinely free — not just changed to something else.
        // Checked directly against the same 'unique:users,email' rule registration/admin
        // account creation actually use, rather than posting to the (globally
        // rate-limited, shared across this whole test run) /register route itself.
        $this->assertTrue(
            \Illuminate\Support\Facades\Validator::make(
                ['email' => 'reusable@example.com'],
                ['email' => 'unique:users,email'],
            )->passes(),
            'The original email should validate as unique again once the account holding it is soft-deleted.'
        );
    }

    public function test_a_soft_deleted_user_disappears_from_user_management(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Gone Person']);

        $target->delete();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Gone Person');
    }

    public function test_a_soft_deleted_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password1!')]);
        $user->delete();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password1!',
        ]);

        $this->assertGuest();
    }

    public function test_admin_can_create_an_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Reviewer',
            'email' => 'new-reviewer@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'reviewer',
        ])->assertSessionDoesntHaveErrors()->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new-reviewer@example.com', 'role' => 'reviewer']);
    }

    /**
     * Regression test: create-account validation errors used to land in the default error
     * bag, same as the batch role/status update form on this same page — so the reopened
     * modal's own error list (which now reads $errors->createAccount specifically, see
     * admin/users/index.blade.php) must actually receive them, not the plain $errors used
     * for the batch-update form's own errors elsewhere on the page.
     */
    public function test_invalid_account_creation_reopens_the_modal_with_its_own_errors(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role' => 'reviewer',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors(['name', 'email', 'password'], null, 'createAccount');

        $page = $this->actingAs($admin)->get(route('admin.users.index'));
        $page->assertOk();

        // The actual error message must render inside the reopened create-account modal
        // (as one of its fields' own inline errors), not just exist in the session —
        // confirms the blade actually reads $errors->createAccount, field by field.
        $modalHeadingPosition = strpos($page->getContent(), 'Create Account</h3>');
        $emailErrorPosition = strpos($page->getContent(), 'must be a valid email address');

        $this->assertNotFalse($modalHeadingPosition);
        $this->assertNotFalse($emailErrorPosition, 'Expected the email validation message to render on the page.');
        $this->assertGreaterThan($modalHeadingPosition, $emailErrorPosition, 'The error message should render after (inside) the modal, not before it.');
    }

    public function test_admin_can_edit_a_users_name_role_and_password_from_the_edit_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Old Name', 'role' => \App\Enums\UserRole::RESEARCHER]);

        $this->actingAs($admin)->patch(route('admin.users.update', $target), [
            'name' => 'New Name',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
            'role' => 'reviewer',
            'status' => 'active',
        ])->assertSessionDoesntHaveErrors()->assertRedirect();

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame(\App\Enums\UserRole::REVIEWER, $target->role);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword1!', $target->password));
    }

    public function test_disabling_a_user_from_the_edit_modal_requires_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update', $target), [
                'name' => $target->name,
                'role' => $target->role->value,
                'status' => 'disabled',
                // status_notes intentionally omitted
            ]);

        $response->assertSessionHasErrors(['status_notes'], null, 'editUser'.$target->id);
        $this->assertNotSame(\App\Enums\AccountStatus::DISABLED, $target->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.users.update', $target), [
            'name' => $target->name,
            'role' => $target->role->value,
            'status' => 'disabled',
            'status_notes' => 'Left the division.',
        ])->assertSessionDoesntHaveErrors()->assertRedirect();

        $target->refresh();
        $this->assertSame(\App\Enums\AccountStatus::DISABLED, $target->status);
        $this->assertSame('Left the division.', $target->status_notes);
        $this->assertNotNull($target->disabled_at);
        $this->assertSame($admin->id, $target->disabled_by);
    }

    public function test_admin_cannot_remove_their_own_admin_role_or_disable_themselves_via_the_edit_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'role' => 'researcher',
            'status' => 'active',
        ])->assertSessionHasErrors(['role'], null, 'editUser'.$admin->id);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'role' => 'admin',
            'status' => 'disabled',
            'status_notes' => 'Trying to disable myself.',
        ])->assertSessionHasErrors(['status'], null, 'editUser'.$admin->id);

        $this->assertSame(\App\Enums\UserRole::ADMIN, $admin->fresh()->role);
        $this->assertSame(\App\Enums\AccountStatus::ACTIVE, $admin->fresh()->status);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('users');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_a_non_admin_cannot_delete_users(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertNotSoftDeleted($target);
    }
}
