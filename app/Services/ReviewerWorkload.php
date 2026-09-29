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
        $variance = $loads->avg(fn ($r) => ($r['after'] - $mean) ** 2) ?? 0;
        $deviation = ($loads->max('after') ?? 0) - $mean;
        $beforeMean = $loads->avg('before') ?? 0;
        $beforeVariance = $loads->avg(fn ($r) => ($r['before'] - $beforeMean) ** 2) ?? 0;
        // Existing imbalance may be repaired incrementally, but never worsened by an override.
        $improving = $variance < $beforeVariance && $deviation <= (($loads->max('before') ?? 0) - $beforeMean);

        return compact('mean', 'variance', 'deviation') + [
            'warning' => $variance > 1 || $deviation > 1,
            'blocked' => ($variance > 2.25 || $deviation > 2) && ! $improving,
        ];
    }
}
