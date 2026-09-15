<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_document_templates', function (Blueprint $table) {
            // A separate column from the legacy `auto_format_options` on purpose — that one
            // belongs entirely to the old HTML/dompdf ('canvas_editor') pipeline's CSS overlay
            // (see pdf/template-shell.blade.php) and is a different shape (a flat font/size/
            // align/line-height "default" + per-section-key overrides). This is the ONLYOFFICE
            // per-chapter engine's own docx-level formatting policy (see
            // ManuscriptFormattingController and scripts/manuscript.py's apply_formatting) —
            // null/absent means "use the template's own document formatting untouched", the
            // same backward-compatible default every existing template already has.
            $table->json('manuscript_format_options')->nullable()->after('docx_key');
        });
    }

    public function down(): void
    {
        Schema::table('submission_document_templates', function (Blueprint $table) {
            $table->dropColumn('manuscript_format_options');
        });
    }
};
