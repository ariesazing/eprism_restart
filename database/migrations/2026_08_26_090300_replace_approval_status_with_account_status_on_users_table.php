<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the account-approval gate (pending/approved/rejected) in favor of a plain
 * active/disabled account status: new accounts are usable immediately, and "disabled"
 * becomes an admin action for accounts no longer in use, not a rejection of a pending
 * request. `approval_notes` is kept (renamed) since its free-text content is still
 * meaningful as "why this account is disabled"; `approved_at`/`approved_by` have no
 * equivalent under the new model (there's no more approval event to record) and are
 * replaced with `disabled_at`/`disabled_by`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('role');
            $table->foreignId('disabled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable()->after('disabled_by');
        });

        // Backfill: a rejected account becomes disabled; pending or approved both
        // become active, since there's no more approval gate to have been pending on.
        DB::table('users')->where('approval_status', 'rejected')->update(['status' => 'disabled']);
        DB::table('users')->where('approval_status', '!=', 'rejected')->update(['status' => 'active']);

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('approval_notes', 'status_notes');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'approved_at', 'approved_by']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('role');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });

        DB::table('users')->where('status', 'disabled')->update(['approval_status' => 'rejected']);
        DB::table('users')->where('status', '!=', 'disabled')->update(['approval_status' => 'approved']);

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('status_notes', 'approval_notes');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['disabled_by']);
            $table->dropColumn(['status', 'disabled_by', 'disabled_at']);
        });
    }
};
