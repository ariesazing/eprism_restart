<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
        });

        // Every already-submitted (non-draft) row entered the review queue before this
        // column existed. Approximate it with updated_at rather than leaving Reviewer
        // Assignment's new column/sort empty for all pre-existing data — updated_at was
        // last touched by whatever status transition (submit/resubmit) put it in its
        // current non-draft state.
        DB::table('research_submissions')
            ->where('status', '!=', 'draft')
            ->update(['submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
