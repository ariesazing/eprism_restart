<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates exactly one account — the admin every other account in the system gets
     * created *through* (admin/users), not a set of ready-made reviewer/researcher demo
     * logins alongside it. Pre-verified (email_verified_at set) and active, so it's usable
     * immediately with no separate email-verification step. Name/email/password are
     * env-overridable (ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD) specifically so seeding a real
     * environment doesn't leave it with the same hardcoded credential every local checkout
     * of this repo also seeds — set them in .env before running db:seed somewhere real.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => env('ADMIN_NAME'),
            'email' => env('ADMIN_EMAIL'),
            'password' => Hash::make(env('ADMIN_PASSWORD')),
            'email_verified_at' => now(),
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->call([
            OrganizationalUnitSeeder::class,
            OrganizationalUnitPositionSeeder::class,
            SubmissionDocumentTemplateSeeder::class,
            RapmTemplateSeeder::class,
        ]);
    }
}
