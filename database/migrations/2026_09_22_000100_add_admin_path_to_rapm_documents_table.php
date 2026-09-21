<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapm_documents', function (Blueprint $table) {
            // A review summary is rendered twice: `path` is the researcher's blind-review copy
            // ("Reviewer 1"), this is the admin copy that also names each reviewer. Null on
            // every other kind of document, and on review summaries generated before this
            // column existed (admins just get `path`, as they always did).
            $table->string('admin_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('rapm_documents', function (Blueprint $table) {
            $table->dropColumn('admin_path');
        });
    }
};
