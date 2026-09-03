<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_document_templates', function (Blueprint $table) {
            // JSON-encoded {font_family, font_size, text_align, line_height}, matching the
            // existing page_options JSON-in-a-text-column convention — nullable, and any
            // omitted property leaves that aspect of the researcher's own formatting alone
            // (see SubmissionPdfComposer::compose() / pdf/template-shell.blade.php).
            $table->longText('auto_format_options')->nullable()->after('page_options');
        });
    }

    public function down(): void
    {
        Schema::table('submission_document_templates', function (Blueprint $table) {
            $table->dropColumn('auto_format_options');
        });
    }
};
