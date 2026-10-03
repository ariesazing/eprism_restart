<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', fn (Blueprint $table) => $table->json('evaluation_results')->nullable());
    }

    public function down(): void
    {
        Schema::table('research_submissions', fn (Blueprint $table) => $table->dropColumn('evaluation_results'));
    }
};
