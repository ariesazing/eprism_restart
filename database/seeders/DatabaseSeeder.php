<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database: the one admin account (see AdminUserSeeder — set
     * ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD in .env before running db:seed somewhere real),
     * then the reference data the app can't run without.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            OrganizationalUnitSeeder::class,
            OrganizationalUnitPositionSeeder::class,
            SubmissionDocumentTemplateSeeder::class,
            RapmTemplateSeeder::class,
        ]);
    }
}
