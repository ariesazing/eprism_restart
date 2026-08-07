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
        Schema::create('submission_readiness_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->unique()->constrained('research_submissions')->cascadeOnDelete();
            $table->string('content_hash', 64);
            $table->unsignedTinyInteger('completeness_percent');
            $table->unsignedTinyInteger('sections_done');
            $table->unsignedTinyInteger('sections_total');
            $table->unsignedTinyInteger('attachments_done');
            $table->unsignedTinyInteger('attachments_total');
            $table->decimal('grammar_percent', 5, 2)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_readiness_assessments');
    }
};
