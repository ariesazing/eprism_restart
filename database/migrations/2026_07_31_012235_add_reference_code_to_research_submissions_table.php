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
            $table->string('reference_code')->nullable()->unique()->after('id');
        });

        foreach (DB::table('research_submissions')->whereNull('reference_code')->get() as $submission) {
            DB::table('research_submissions')
                ->where('id', $submission->id)
                ->update(['reference_code' => sprintf('EPRISM-%s-%06d', $submission->created_at ? date('Y', strtotime($submission->created_at)) : now()->year, $submission->id)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn('reference_code');
        });
    }
};
