<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->string('editor_engine')->default('canvas_editor')->after('classification');
        });
    }

    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn('editor_engine');
        });
    }
};
