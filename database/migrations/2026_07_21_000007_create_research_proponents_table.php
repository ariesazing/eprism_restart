<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_proponents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_initial')->nullable();
            $table->string('email');
            $table->string('contact_number');
            $table->string('photo_path')->nullable();
            $table->string('organizational_unit');
            $table->string('organizational_unit_type');
            $table->string('position');
            $table->string('school_id')->nullable();
            $table->boolean('is_lead')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (DB::table('research_submissions')->get() as $submission) {
            if (trim((string) $submission->proponent_last_name) === '' && trim((string) $submission->proponent_first_name) === '') {
                continue;
            }

            DB::table('research_proponents')->insert([
                'research_submission_id' => $submission->id,
                'last_name' => $submission->proponent_last_name,
                'first_name' => $submission->proponent_first_name,
                'middle_initial' => $submission->proponent_middle_initial,
                'email' => $submission->proponent_email,
                'contact_number' => $submission->proponent_contact_number,
                'photo_path' => $submission->proponent_photo_path,
                'organizational_unit' => $submission->organizational_unit,
                'organizational_unit_type' => $submission->organizational_unit_type,
                'position' => $submission->position,
                'school_id' => $submission->school_id,
                'is_lead' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn([
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

    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
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

        Schema::dropIfExists('research_proponents');
    }
};
