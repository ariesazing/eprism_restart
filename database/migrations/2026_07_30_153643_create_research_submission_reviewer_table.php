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
        Schema::create('research_submission_reviewer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['research_submission_id', 'reviewer_id'], 'submission_reviewer_unique');
        });

        foreach (DB::table('research_submissions')->whereNotNull('assigned_reviewer_id')->get() as $submission) {
            DB::table('research_submission_reviewer')->insert([
                'research_submission_id' => $submission->id,
                'reviewer_id' => $submission->assigned_reviewer_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_reviewer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->foreignId('assigned_reviewer_id')->nullable()->after('researcher_id')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('research_submission_reviewer')->orderBy('id')->get() as $pivot) {
            DB::table('research_submissions')
                ->where('id', $pivot->research_submission_id)
                ->whereNull('assigned_reviewer_id')
                ->update(['assigned_reviewer_id' => $pivot->reviewer_id]);
        }

        Schema::dropIfExists('research_submission_reviewer');
    }
};
