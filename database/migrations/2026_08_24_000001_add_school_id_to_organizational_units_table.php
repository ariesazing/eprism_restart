<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            // Nullable: non-school units (e.g. the Division Office) have no DepEd school ID.
            $table->string('school_id')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->dropUnique(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
