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
        Schema::create('similarity_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained('research_submissions')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('text_hash', 64)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('matched_words')->default(0);
            $table->decimal('score', 5, 2)->nullable();
            // The exact paragraphs that were checked (encrypted at rest, like the sections
            // they came from) — matches point into these by paragraph index + byte offset, so
            // a report keeps showing what was actually checked even after the chapters change.
            $table->longText('document')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['research_submission_id', 'id']);
        });

        Schema::create('similarity_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('similarity_check_id')->constrained('similarity_checks')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('source_type', 20);
            $table->string('title', 500)->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('matched_words');
            $table->decimal('percent', 5, 2);
            // [{para, start, end, words}] — byte offsets into that paragraph's text.
            $table->json('spans');
            $table->timestamps();

            $table->index(['similarity_check_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('similarity_matches');
        Schema::dropIfExists('similarity_checks');
    }
};
