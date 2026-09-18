<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_windows', function (Blueprint $table) {
            $table->dropUnique(['classification']);
            $table->string('research_type')->nullable()->after('classification');
        });

        // Backfill: each existing proposal/completed row becomes its "basic" counterpart,
        // and is duplicated into a new "action" row with the same settings — so an
        // admin's existing configuration keeps applying exactly as before instead of the
        // newly-split "action" windows silently defaulting open. The memorandum file
        // itself isn't duplicated (each research type now needs its own upload).
        foreach (DB::table('submission_windows')->get() as $row) {
            DB::table('submission_windows')->where('id', $row->id)->update(['research_type' => 'basic']);

            DB::table('submission_windows')->insert([
                'research_type' => 'action',
                'classification' => $row->classification,
                'is_open' => $row->is_open,
                'opens_at' => $row->opens_at,
                'closes_at' => $row->closes_at,
                'memorandum_path' => null,
                'memorandum_original_name' => null,
                'updated_by' => $row->updated_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::table('submission_windows', function (Blueprint $table) {
            $table->unique(['research_type', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::table('submission_windows', function (Blueprint $table) {
            $table->dropUnique(['research_type', 'classification']);
        });

        DB::table('submission_windows')->where('research_type', 'action')->delete();

        Schema::table('submission_windows', function (Blueprint $table) {
            $table->dropColumn('research_type');
            $table->unique('classification');
        });
    }
};
