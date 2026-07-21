<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'System Administrator',
            'email' => 'admin@eprism.test',
            'approval_status' => ApprovalStatus::APPROVED,
        ]);

        User::factory()->reviewer()->create([
            'name' => 'Default Reviewer',
            'email' => 'reviewer@eprism.test',
        ]);

        User::factory()->pendingApproval()->create([
            'name' => 'Researcher Account',
            'email' => 'researcher@eprism.test',
        ]);

        $this->call([
            OrganizationalUnitSeeder::class,
            OrganizationalUnitPositionSeeder::class,
        ]);
    }
}
