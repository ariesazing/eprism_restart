<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', fn (Blueprint $table) => $table->json('manuscript')->nullable());
        Schema::create('manuscript_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->restrictOnDelete();
            $table->uuid('attempt')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('manuscript_versions')->restrictOnDelete();
            $table->foreignId('research_snapshot_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('state')->default('processing');
            $table->string('docx_path');
            $table->string('docx_hash', 64);
            $table->string('template_path');
            $table->string('template_hash', 64);
            $table->json('metadata');
            $table->json('attachments');
            $table->json('validation')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('final_pdf_path')->nullable();
            $table->string('final_pdf_hash', 64)->nullable();
            $table->text('final_pdf_owner_password')->nullable();
            $table->text('final_pdf_error')->nullable();
            $table->timestamps();
        });
        Schema::table('reviews', fn (Blueprint $table) => $table->foreignId('manuscript_version_id')->nullable()->constrained()->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId('manuscript_version_id'));
        Schema::dropIfExists('manuscript_versions');
        Schema::table('research_submissions', fn (Blueprint $table) => $table->dropColumn('manuscript'));
    }
};
