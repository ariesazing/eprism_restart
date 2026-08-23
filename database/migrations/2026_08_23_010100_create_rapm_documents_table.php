<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapm_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained('research_submissions')->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('fingerprint')->nullable();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['research_submission_id', 'kind', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapm_documents');
    }
};
