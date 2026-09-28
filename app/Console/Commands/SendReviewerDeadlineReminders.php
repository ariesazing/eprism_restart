<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Notifications\ReviewerDeadlineReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendReviewerDeadlineReminders extends Command
{
    protected $signature = 'app:send-reviewer-deadline-reminders';

    protected $description = 'Send 24h and 12h deadline reminders to reviewers with pending evaluations';

    public function handle(): int
    {
        $now = now();

        $submissions = ResearchSubmission::query()
            ->where('status', SubmissionStatus::UNDER_REVIEW)
            ->with(['reviewers', 'reviews'])
            ->get();

        $sentCount = 0;

        foreach ($submissions as $submission) {
            foreach ($submission->reviewers as $reviewer) {
                // Check if reviewer has already submitted their review
                $completed = $submission->reviews
                    ->where('reviewer_id', $reviewer->id)
                    ->whereNotNull('submitted_at')
                    ->isNotEmpty();

                if ($completed) {
                    continue;
                }

                $deadlineAt = $reviewer->pivot?->deadline_at ? Carbon::parse($reviewer->pivot->deadline_at) : null;
                if (! $deadlineAt || $deadlineAt->isPast()) {
                    continue;
                }

                $diffMinutes = $now->diffInMinutes($deadlineAt, false);
                if ($diffMinutes <= 0) {
                    continue;
                }

                $diffHours = (int) ceil($diffMinutes / 60);

                // Thresholds: 24h (12 < hours <= 24) or 12h (0 < hours <= 12)
                $threshold = match (true) {
                    $diffHours <= 12 => 12,
                    $diffHours <= 24 => 24,
                    default => null,
                };

                if ($threshold === null) {
                    continue;
                }

                $cacheKey = "deadline_reminder_{$submission->id}_{$reviewer->id}_{$threshold}";
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $reviewer->notify(new ReviewerDeadlineReminderNotification($submission, $diffHours, $deadlineAt));
                Cache::put($cacheKey, true, now()->addDays(2));
                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} reviewer deadline reminders.");

        return self::SUCCESS;
    }
}
