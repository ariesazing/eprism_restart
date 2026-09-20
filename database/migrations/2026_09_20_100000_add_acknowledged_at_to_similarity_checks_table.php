<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('similarity_checks', function (Blueprint $table) {
            // When the researcher was told this check finished — by opening its report, or by
            // dismissing the "your check is done" popup. A finished check with this still null is
            // one they haven't heard about yet (they navigated away while it ran).
            $table->timestamp('acknowledged_at')->nullable()->after('completed_at');
        });

        // Checks that already exist predate the popup; without this every one of them would
        // announce itself the first time its owner loads a page after the deploy.
        DB::table('similarity_checks')->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('similarity_checks', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
