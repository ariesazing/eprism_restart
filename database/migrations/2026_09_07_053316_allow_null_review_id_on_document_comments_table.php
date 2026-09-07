<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SubmissionDecisionService::evaluate() hard-deletes every Review row for a submission once
 * a proposal is unanimously approved and promoted to "completed" (clearing reviewer
 * assignments so a fresh round can be assigned for the completed-research phase). Until now
 * document_comments.review_id was `cascadeOnDelete()`, so that delete cascaded straight
 * through to every reviewer comment ever left on the submission — across *every* snapshot
 * version, not just the current one — permanently destroying the version-history record a
 * researcher relies on to see what reviewers said. The comment itself (author, body,
 * snapshot, anchor position) is independently meaningful and worth keeping even once the
 * review it was made under is gone; only the FK needs to survive that, not the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->dropForeign(['review_id']);
        });

        Schema::table('document_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('review_id')->nullable()->change();
        });

        Schema::table('document_comments', function (Blueprint $table) {
            $table->foreign('review_id')->references('id')->on('reviews')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->dropForeign(['review_id']);
        });

        // Any comment orphaned by the forward migration (review_id backfilled to null by a
        // now-completed promotion) has no review to point back at — delete rather than leave
        // it violating the NOT NULL constraint the original schema had.
        DB::table('document_comments')->whereNull('review_id')->delete();

        Schema::table('document_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('review_id')->nullable(false)->change();
        });

        Schema::table('document_comments', function (Blueprint $table) {
            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
        });
    }
};
