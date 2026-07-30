<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->string('organizational_unit')->nullable()->after('classification');
            $table->string('organizational_unit_type')->nullable()->after('organizational_unit');
            $table->string('school_id')->nullable()->after('organizational_unit_type');
        });

        foreach (DB::table('research_submissions')->get() as $submission) {
            $lead = DB::table('research_proponents')
                ->where('research_submission_id', $submission->id)
                ->orderByDesc('is_lead')
                ->orderBy('sort_order')
                ->first();

            if ($lead === null) {
                continue;
            }

            DB::table('research_submissions')->where('id', $submission->id)->update([
                'organizational_unit' => $lead->organizational_unit,
                'organizational_unit_type' => $lead->organizational_unit_type,
                'school_id' => $lead->school_id,
            ]);
        }

        Schema::table('research_proponents', function (Blueprint $table) {
            $table->dropColumn(['organizational_unit', 'organizational_unit_type', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_proponents', function (Blueprint $table) {
            $table->string('organizational_unit')->nullable()->after('photo_path');
            $table->string('organizational_unit_type')->nullable()->after('organizational_unit');
            $table->string('school_id')->nullable()->after('position');
        });

        foreach (DB::table('research_submissions')->get() as $submission) {
            DB::table('research_proponents')
                ->where('research_submission_id', $submission->id)
                ->update([
                    'organizational_unit' => $submission->organizational_unit,
                    'organizational_unit_type' => $submission->organizational_unit_type,
                    'school_id' => $submission->school_id,
                ]);
        }

        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn(['organizational_unit', 'organizational_unit_type', 'school_id']);
        });
    }
};
