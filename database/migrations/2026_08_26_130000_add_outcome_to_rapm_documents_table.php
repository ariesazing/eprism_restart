<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapm_documents', function (Blueprint $table) {
            // Only meaningful for kind=review_summary — records whether that review round
            // resulted in approval or a revision request, independent of the submission's
            // current (possibly since-changed) status. Reviewers use this to know which
            // review summaries they're allowed to preview.
            $table->string('outcome')->nullable()->after('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('rapm_documents', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
