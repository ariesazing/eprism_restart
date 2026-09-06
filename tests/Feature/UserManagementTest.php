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
