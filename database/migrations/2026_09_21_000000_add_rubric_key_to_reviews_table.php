<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Which of App\Evaluation\ResearchEvaluationRubric's four templates this review's
            // own criteria_scores were scored against (e.g. 'basic_proposal') — recorded once
            // at submission time, never re-derived from the submission's *current*
            // research_type/classification later, since a promoted proposal has its
            // classification overwritten to 'completed' in place (see
            // SubmissionDecisionService::evaluate()) and an old review must still be readable
            // against the rubric it was actually scored under. Nullable: a review created
            // outside the normal reviewer-scoring flow (e.g. DocumentCommentController's
            // placeholder row before a reviewer has opened the scoring form) may not have one
            // yet.
            $table->string('rubric_key')->nullable()->after('reviewer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('rubric_key');
        });
    }
};
