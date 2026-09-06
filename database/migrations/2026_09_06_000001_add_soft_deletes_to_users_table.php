<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin "delete account" is a soft delete (see UserManagementController::destroy): the
 * row stays in place so submissions, reviews, and activity-log entries that point at
 * this user keep resolving, but the account can no longer authenticate (the SoftDeletes
 * global scope hides it from the auth provider) and it drops out of user management.
 *
 * The email column keeps its unique constraint, so a soft-deleted address stays
 * reserved and can't be registered again — matching the "one account per email" rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
