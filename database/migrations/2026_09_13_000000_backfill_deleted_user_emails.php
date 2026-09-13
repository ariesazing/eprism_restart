<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * UserManagementController::destroy() rewrites a deleted account's email to a synthetic
 * deleted-{id}@eprism.invalid placeholder before soft-deleting it, freeing the real
 * address for re-registration. Accounts soft-deleted before that rewrite was added still
 * hold their real email in the (unique) email column, so re-registering that address
 * still fails with "already registered" even though nobody can sign into the deleted
 * account anymore. This backfills those older soft-deleted rows onto the same scheme.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('email', 'not like', 'deleted-%@eprism.invalid')
            ->get(['id'])
            ->each(fn ($user) => DB::table('users')->where('id', $user->id)->update([
                'email' => "deleted-{$user->id}@eprism.invalid",
            ]));
    }

    public function down(): void
    {
        // The real addresses weren't preserved on the row (see
        // UserManagementController::destroy), so there's nothing to restore them to.
    }
};
