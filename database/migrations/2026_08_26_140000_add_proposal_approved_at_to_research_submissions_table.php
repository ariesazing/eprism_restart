<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            // Durable record that this research's proposal phase passed review — set once,
            // when SubmissionDecisionService promotes proposal->completed, and never cleared
            // afterward. Needed because the promotion itself resets status back to draft and
            // deletes the round's reviews to make way for the completed-research phase, so
            // without this there would be no lasting trace that the proposal was ever approved.
            $table->timestamp('proposal_approved_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn('proposal_approved_at');
        });
    }
};
