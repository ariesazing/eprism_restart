<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn(['course', 'authors', 'abstract', 'keywords']);
        });
    }

    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->string('course')->default('')->after('classification');
            $table->text('authors')->nullable()->after('course');
            $table->text('abstract')->nullable()->after('authors');
            $table->text('keywords')->nullable()->after('abstract');
        });
    }
};
