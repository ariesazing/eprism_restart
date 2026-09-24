<?php

namespace App\Services;

use App\Models\SubmissionWindow;
use App\Models\User;
use App\Notifications\SubmissionWindowChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubmissionWindowNotifier
{
    public function check(SubmissionWindow $window): void
    {
        DB::transaction(function () use ($window) {
            $window = SubmissionWindow::query()->lockForUpdate()->findOrFail($window->id);
            $open = $window->isCurrentlyOpen();
            $previous = $window->last_notified_open;
            if ($previous === $open) {
                return;
            }

            $window->last_notified_open = $open;
            $window->saveQuietly();

            // Initial installation establishes a baseline without emailing an old event.
            if ($previous === null) {
                return;
            }

            User::query()->whereNotNull('email')->chunkById(200, function ($users) use ($window, $open) {
                Notification::send($users, new SubmissionWindowChanged($window->research_type, $window->classification, $open));
            });
        });
    }

    public function checkAll(): void
    {
        SubmissionWindow::query()->each(fn ($window) => $this->check($window));
    }
}
