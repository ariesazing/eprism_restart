<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_concerns', function (Blueprint $table) {
            $table->unsignedBigInteger('research_submission_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // General concerns have no submission; retain nullability to preserve those records.
    }
};
