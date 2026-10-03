<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_discussion_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->unique(['research_submission_id', 'user_id'], 'discussion_reads_submission_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_discussion_reads');
    }
};
