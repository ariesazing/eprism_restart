<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submission_reviewer', function (Blueprint $table) {
            $table->timestamp('evaluation_opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('research_submission_reviewer', function (Blueprint $table) {
            $table->dropColumn('evaluation_opened_at');
        });
    }
};
