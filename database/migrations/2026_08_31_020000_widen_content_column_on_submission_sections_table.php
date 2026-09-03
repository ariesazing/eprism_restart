<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `content` is base64-encoded encrypted data (SubmissionSection casts it 'encrypted'),
 * which inflates well past the original text — a plain TEXT column (MySQL's ~64KB cap)
 * was already outgrown by long chapters, throwing "Data too long for column 'content'".
 * `content_html` was already widened to LONGTEXT for the same reason (see
 * 2026_08_18_090000_add_content_html_to_submission_sections_table.php) — this brings
 * `content` in line with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_sections', function (Blueprint $table) {
            $table->longText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('submission_sections', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }
};
