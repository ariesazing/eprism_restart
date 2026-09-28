<?php

namespace App\Services;

use App\Models\ResearchProponent;
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

            $sent = [];
            User::query()->whereNotNull('email')->chunkById(200, function ($users) use ($window, $open, &$sent) {
                foreach ($users as $user) {
                    $email = mb_strtolower(trim($user->email));
                    if ($email === '' || isset($sent[$email])) {
                        continue;
                    }
                    $user->notify(new SubmissionWindowChanged($window->research_type, $window->classification, $open));
                    $sent[$email] = true;
                }
            });
            ResearchProponent::query()->whereNotNull('email')->chunkById(200, function ($proponents) use ($window, $open, &$sent) {
                foreach ($proponents as $proponent) {
                    $email = mb_strtolower(trim($proponent->email));
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($sent[$email])) {
                        continue;
                    }
                    Notification::route('mail', [$email => $proponent->documentName()])
                        ->notify(new SubmissionWindowChanged($window->research_type, $window->classification, $open));
                    $sent[$email] = true;
                }
            });
        });
    }

    public function checkAll(): void
    {
        SubmissionWindow::query()->each(fn ($window) => $this->check($window));
    }
}
