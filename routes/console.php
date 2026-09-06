<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs well inside the 6-hour verification window (see PruneUnverifiedUsers) so an
// account is never left stale much longer than the window actually promises.
Schedule::command('users:prune-unverified')->everyThirtyMinutes();
