<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Run manually with: php artisan db:seed --class=TestUserSeeder
 * Re-running resets these test accounts to their documented credentials and status.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedAccounts(UserRole::REVIEWER, 5);
            $this->seedAccounts(UserRole::RESEARCHER, 15);
        });
    }

    private function seedAccounts(UserRole $role, int $count): void
    {
        for ($number = 1; $number <= $count; $number++) {
            $user = User::withTrashed()->firstOrNew([
                'email' => "{$role->value}{$number}@eprism.test",
            ]);

            $user->forceFill([
                'name' => ucfirst($role->value)." {$number}",
                'password' => Hash::make('P@ssw'.($count - $number + 1).'rd'),
                'role' => $role,
                'email_verified_at' => now(),
                'status' => AccountStatus::ACTIVE,
                'disabled_at' => null,
                'disabled_by' => null,
                'status_notes' => null,
                'deleted_at' => null,
            ])->save();
        }
    }
}
