<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Creates the one account every other account in the system gets created *through*
 * (admin/users), not a set of ready-made reviewer/researcher demo logins alongside it.
 * Pre-verified (email_verified_at set) and active, so it's usable immediately with no
 * separate email-verification step. Name/email/password come from ADMIN_NAME/ADMIN_EMAIL/
 * ADMIN_PASSWORD (config/eprism.php) specifically so seeding a real environment doesn't
 * leave it with the same hardcoded credential every local checkout of this repo also
 * seeds — set them in .env before seeding somewhere real.
 *
 * Does nothing if that email already has an account, so re-seeding never fails on the
 * unique index or overwrites a password the admin has since changed.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * @return array{name: string, email: string, password: string}
     *
     * @throws RuntimeException when any of the three is missing or unusable
     */
    public static function credentials(): array
    {
        $validator = Validator::make((array) config('eprism.admin'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD must all be set (password at least 8 characters). '
                .implode(' ', $validator->errors()->all())
            );
        }

        return $validator->validated();
    }

    public function run(): void
    {
        $admin = self::credentials();

        if (User::query()->where('email', $admin['email'])->exists()) {
            return;
        }

        User::factory()->admin()->create([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'password' => Hash::make($admin['password']),
            'email_verified_at' => now(),
            'status' => AccountStatus::ACTIVE,
        ]);
    }
}
