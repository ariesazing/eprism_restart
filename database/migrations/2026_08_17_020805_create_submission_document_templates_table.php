<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->unique();
            $table->longText('content')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_document_templates');
    }
};
