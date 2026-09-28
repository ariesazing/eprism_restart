<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_snapshots', function (Blueprint $table) {
            $table->timestamp('files_pruned_at')->nullable();
            $table->longText('similarity_text')->nullable();
        });
        Schema::table('manuscript_versions', fn (Blueprint $table) => $table->timestamp('files_pruned_at')->nullable());
        Schema::table('similarity_checks', fn (Blueprint $table) => $table->string('source_scope')->default('web'));
    }

    public function down(): void
    {
        Schema::table('research_snapshots', fn (Blueprint $table) => $table->dropColumn(['files_pruned_at', 'similarity_text']));
        Schema::table('manuscript_versions', fn (Blueprint $table) => $table->dropColumn('files_pruned_at'));
        Schema::table('similarity_checks', fn (Blueprint $table) => $table->dropColumn('source_scope'));
    }
};
