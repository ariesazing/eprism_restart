<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            // Lets an admin retire a unit (a school that closed/merged) from new
            // submissions' dropdowns without deleting it — existing submissions that
            // already reference it keep working (see ResearchSubmissionController's
            // stale-organizational-unit fallback).
            $table->boolean('is_active')->default(true)->after('organizational_unit_type');
        });
    }

    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
