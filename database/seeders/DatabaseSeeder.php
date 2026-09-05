<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
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
            'name' => 'System Administrator Test',
            'email' => 'admin@eprism.test',
            'status' => AccountStatus::ACTIVE,
        ]);

        User::factory()->reviewer()->create([
            'name' => 'Default Reviewer Test',
            'email' => 'reviewer@eprism.test',
        ]);

        User::factory()->create([
            'name' => 'Researcher Account Test',
            'email' => 'researcher@eprism.test',
        ]);

        $this->call([
            OrganizationalUnitSeeder::class,
            OrganizationalUnitPositionSeeder::class,
            SubmissionDocumentTemplateSeeder::class,
            RapmTemplateSeeder::class,
        ]);
    }
}
