<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained('research_submissions')->cascadeOnDelete();
            $table->string('section_key');
            $table->string('label');
            $table->string('type')->default('rich_text');
            $table->text('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['research_submission_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_sections');
    }
};
