<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_windows', function (Blueprint $table) {
            $table->string('memorandum_path')->nullable()->after('closes_at');
            $table->string('memorandum_original_name')->nullable()->after('memorandum_path');
        });
    }

    public function down(): void
    {
        Schema::table('submission_windows', function (Blueprint $table) {
            $table->dropColumn(['memorandum_path', 'memorandum_original_name']);
        });
    }
};
