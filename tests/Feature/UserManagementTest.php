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
