<?php

use App\Services\ManuscriptService;
use App\Services\SubmissionWindowNotifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs well inside the 6-hour verification window (see PruneUnverifiedUsers) so an
// account is never left stale much longer than the window actually promises.
Schedule::command('users:prune-unverified')->everyThirtyMinutes();

Schedule::call(fn () => app(SubmissionWindowNotifier::class)->checkAll())
    ->name('submissions:notify-window-changes')->everyMinute()->withoutOverlapping();

// See ManuscriptService::recoverStuckSessions() for what this actually does and why — kept
// as a thin scheduler entry so the recovery logic itself stays directly unit-testable rather
// than only reachable through Laravel's Schedule internals.
Schedule::call(fn () => app(ManuscriptService::class)->recoverStuckSessions())
    ->name('manuscripts:expire-save-waits')->everyMinute()->withoutOverlapping();
