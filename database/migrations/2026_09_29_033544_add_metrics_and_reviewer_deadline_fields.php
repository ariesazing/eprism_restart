<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('submission_readiness_assessments', 'metrics')) {
            Schema::table('submission_readiness_assessments', function (Blueprint $table) {
                $table->json('metrics')->nullable();
            });
        }

        if (! Schema::hasColumn('research_submission_reviewer', 'deadline_at')) {
            Schema::table('research_submission_reviewer', function (Blueprint $table) {
                $table->timestamp('deadline_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('submission_readiness_assessments', 'metrics')) {
            Schema::table('submission_readiness_assessments', function (Blueprint $table) {
                $table->dropColumn('metrics');
            });
        }

        if (Schema::hasColumn('research_submission_reviewer', 'deadline_at')) {
            Schema::table('research_submission_reviewer', function (Blueprint $table) {
                $table->dropColumn('deadline_at');
            });
        }
    }
};
