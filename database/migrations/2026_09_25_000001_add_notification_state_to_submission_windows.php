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
            $table->boolean('last_notified_open')->nullable();
        });

        DB::table('submission_windows')->orderBy('id')->each(function ($window) {
            $open = $window->is_open
                && (! $window->opens_at || now()->gte($window->opens_at))
                && (! $window->closes_at || now()->lte($window->closes_at));
            DB::table('submission_windows')->where('id', $window->id)->update(['last_notified_open' => $open]);
        });
    }

    public function down(): void
    {
        Schema::table('submission_windows', fn (Blueprint $table) => $table->dropColumn('last_notified_open'));
    }
};
