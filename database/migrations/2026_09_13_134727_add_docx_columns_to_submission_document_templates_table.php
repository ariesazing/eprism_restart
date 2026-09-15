<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_document_templates', function (Blueprint $table) {
            $table->string('docx_path')->nullable()->after('footer_html');
            $table->string('docx_key')->nullable()->after('docx_path');
        });
    }

    public function down(): void
    {
        Schema::table('submission_document_templates', function (Blueprint $table) {
            $table->dropColumn(['docx_path', 'docx_key']);
        });
    }
};
