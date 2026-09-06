<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

/**
 * Self-registered accounts get one window to verify their email before they're purged.
 * Admin-created accounts are stamped with email_verified_at at creation time (see
 * UserManagementController), so whereNull() here never touches them regardless of age.
 *
 * A row surviving this long has never been touched by anything behind the 'verified'
 * middleware, so there's no dependent data to worry about — the only real cost of
 * leaving it in place is that its email stays reserved (unique constraint) and can't
 * be used to register again. That's exactly why this is a forceDelete(): the User model
 * soft-deletes by default (admin "delete account"), but an abandoned unverified
 * registration should be gone for good so the address is freed.
 */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--hours=6 : Age in hours after which an unverified account is deleted}';

    protected $description = 'Delete self-registered accounts that never verified their email within the grace period';

    public function handle(ActivityLogger $activity): int
    {
        $hours = (int) $this->option('hours');

        $staleUsers = User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHours($hours))
            ->get();

        foreach ($staleUsers as $user) {
            $activity->log(null, 'auth.unverified_pruned', $user, $user->name.' ('.$user->email.') was deleted after not verifying their email within '.$hours.' hour(s).');
            $user->forceDelete();
        }

        $this->info("Pruned {$staleUsers->count()} unverified account(s) older than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
