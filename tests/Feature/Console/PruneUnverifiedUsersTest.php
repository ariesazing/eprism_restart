<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_unverified_users_past_the_grace_period(): void
    {
        $stale = User::factory()->unverified()->create(['created_at' => now()->subHours(7)]);

        $this->artisan('users:prune-unverified')->assertExitCode(0);

        $this->assertModelMissing($stale);
    }

    public function test_it_leaves_unverified_users_still_within_the_grace_period(): void
    {
        $recent = User::factory()->unverified()->create(['created_at' => now()->subHours(2)]);

        $this->artisan('users:prune-unverified');

        $this->assertModelExists($recent);
    }

    public function test_it_never_touches_verified_users_regardless_of_age(): void
    {
        $verified = User::factory()->create(['created_at' => now()->subDays(30)]);

        $this->artisan('users:prune-unverified');

        $this->assertModelExists($verified);
    }

    public function test_the_grace_period_is_configurable(): void
    {
        $user = User::factory()->unverified()->create(['created_at' => now()->subHours(2)]);

        $this->artisan('users:prune-unverified', ['--hours' => 1])->assertExitCode(0);

        $this->assertModelMissing($user);
    }
}
