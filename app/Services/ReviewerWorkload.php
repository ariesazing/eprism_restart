<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Collection;

class ReviewerWorkload
{
    public const ACTIVE_STATUSES = ['submitted', 'resubmitted', 'under_review'];

    public function reviewers(): Collection
    {
        return User::query()->where('role', UserRole::REVIEWER->value)->where('status', 'active')
            ->withCount(['assignedSubmissions' => fn ($q) => $q->whereIn('research_submissions.status', self::ACTIVE_STATUSES)])
            ->orderBy('assigned_submissions_count')->orderBy('name')->get();
    }

    public function project(Collection $reviewers, ResearchSubmission $submission, array $selected): array
    {
        $existing = $submission->reviewers()->pluck('users.id')->all();
        $counted = in_array($submission->status->value, self::ACTIVE_STATUSES, true);
        $loads = $reviewers->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name,
            'before' => $r->assigned_submissions_count,
            'after' => $r->assigned_submissions_count + ($counted ? (int) in_array($r->id, $selected) - (int) in_array($r->id, $existing) : 0),
        ]);
        $mean = $loads->avg('after') ?? 0;
        $maximum = $loads->max('after') ?? 0;
        $spread = $maximum - ($loads->min('after') ?? 0);
        $deviation = $maximum - $mean;
        // Compare integer totals so fractional averages cannot shift a boundary.
        $count = $loads->count();
        $excess = $maximum * $count - $loads->sum('after');
        $blocked = $count > 0 && $excess >= 2 * $count;

        return compact('mean', 'deviation', 'spread') + [
            'balanced' => $spread <= 1,
            'warning' => ! $blocked && $excess > $count,
            'blocked' => $blocked,
        ];
    }
}
