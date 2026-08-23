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
        Schema::table('document_comments', function (Blueprint $table) {
            $table->foreignId('research_snapshot_id')->nullable()->after('research_submission_id')
                ->constrained('research_snapshots')->nullOnDelete();
        });

        // Backfill: comments predate this column, so there's no stored link to the snapshot
        // they were anchored against. Approximate it as the newest snapshot that already
        // existed at the moment the comment was created — the same snapshot the reviewer
        // would have been looking at in the browser when they made it.
        DB::table('document_comments')->orderBy('id')->each(function ($comment) {
            $snapshotId = DB::table('research_snapshots')
                ->where('research_submission_id', $comment->research_submission_id)
                ->where('generated_at', '<=', $comment->created_at)
                ->orderByDesc('version')
                ->value('id');

            if ($snapshotId !== null) {
                DB::table('document_comments')->where('id', $comment->id)->update(['research_snapshot_id' => $snapshotId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('research_snapshot_id');
        });
    }
};
