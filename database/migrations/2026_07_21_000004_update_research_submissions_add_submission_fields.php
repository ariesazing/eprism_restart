<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->string('research_type')->default('basic')->after('status');
            $table->string('classification')->default('proposal')->after('research_type');
            $table->string('proponent_last_name')->default('')->after('classification');
            $table->string('proponent_first_name')->default('')->after('proponent_last_name');
            $table->string('proponent_middle_initial')->nullable()->after('proponent_first_name');
            $table->string('proponent_email')->default('')->after('proponent_middle_initial');
            $table->string('proponent_contact_number')->default('')->after('proponent_email');
            $table->string('proponent_photo_path')->nullable()->after('proponent_contact_number');
            $table->string('organizational_unit')->default('')->after('proponent_photo_path');
            $table->string('organizational_unit_type')->default('school')->after('organizational_unit');
            $table->string('position')->default('')->after('organizational_unit_type');
            $table->string('school_id')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'research_type',
                'classification',
                'proponent_last_name',
                'proponent_first_name',
                'proponent_middle_initial',
                'proponent_email',
                'proponent_contact_number',
                'proponent_photo_path',
                'organizational_unit',
                'organizational_unit_type',
                'position',
                'school_id',
            ]);
        });
    }
};
