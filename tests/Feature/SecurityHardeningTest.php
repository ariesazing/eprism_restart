<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // These tests deliberately exhaust rate limiters — leave a clean slate for
        // whatever test runs next in the same (shared, in-process array cache) suite.
        Cache::flush();

        parent::tearDown();
    }

    public function test_registration_rejects_a_password_that_fails_the_complexity_policy(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Weak Password User',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_registration_accepts_a_password_meeting_the_complexity_policy(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Strong Password User',
            'email' => 'strong@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertAuthenticated();
    }

    public function test_logged_in_password_change_also_enforces_the_complexity_policy(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_registration_is_rate_limited(): void
    {
        Cache::flush();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register'), [
                'name' => "User $i",
                'email' => "throttle-test-$i@example.com",
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

            // register() auto-logs the new account in — the route is guest-only, so
            // without logging back out every subsequent attempt in this loop would be
            // redirected by the 'guest' middleware before ever reaching the throttle
            // check at all, masking whatever the rate limiter actually did.
            Auth::guard('web')->logout();
        }

        $this->post(route('register'), [
            'name' => 'One Too Many',
            'email' => 'throttle-test-overflow@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertStatus(429);
    }

    public function test_password_reset_link_requests_are_rate_limited(): void
    {
        Cache::flush();

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertStatus(429);
    }
}
