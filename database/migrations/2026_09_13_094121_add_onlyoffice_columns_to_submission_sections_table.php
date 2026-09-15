<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_sections', function (Blueprint $table) {
            $table->string('onlyoffice_path')->nullable()->after('content_html');
            $table->string('onlyoffice_key')->nullable()->after('onlyoffice_path');
        });
    }

    public function down(): void
    {
        Schema::table('submission_sections', function (Blueprint $table) {
            $table->dropColumn(['onlyoffice_path', 'onlyoffice_key']);
        });
    }
};
