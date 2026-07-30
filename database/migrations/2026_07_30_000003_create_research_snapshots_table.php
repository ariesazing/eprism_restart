<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained('research_submissions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['research_submission_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_snapshots');
    }
};
