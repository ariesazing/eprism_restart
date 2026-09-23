<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\ActivityLog;
use App\Models\ResearchSubmission;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate statistics shared by the admin dashboard's "Categorization Metrics"/"Research
 * Tracking" panels and the fuller Reports page — kept in one place so both surfaces compute
 * the same numbers the same way instead of drifting apart.
 */
class SubmissionStatisticsService
{
    public function totalSubmissions(): int
    {
        // Count each submitted phase once, preserving the proposal when its record
        // becomes a completed draft. Revision rounds do not add submissions.
        return ResearchSubmission::query()
            ->where('status', '!=', SubmissionStatus::DRAFT->value)
            ->count()
            + ResearchSubmission::query()
                ->where('classification', 'completed')
                ->whereNotNull('proposal_approved_at')
                ->count();
    }

    /**
     * @return Collection<string, int> keyed "{research_type}:{classification}" => count
     */
    public function categorization(): Collection
    {
        return ResearchSubmission::query()
            ->select('research_type', 'classification', DB::raw('count(*) as aggregate'))
            ->groupBy('research_type', 'classification')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->research_type}:{$row->classification}" => (int) $row->aggregate]);
    }

    /**
     * @return array{submitted: int, on_evaluation: int, evaluated: int, on_revision: int}
     */
    public function stages(): array
    {
        $statusCounts = ResearchSubmission::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'submitted' => (int) ($statusCounts[SubmissionStatus::SUBMITTED->value] ?? 0) + (int) ($statusCounts[SubmissionStatus::RESUBMITTED->value] ?? 0),
            'on_evaluation' => (int) ($statusCounts[SubmissionStatus::UNDER_REVIEW->value] ?? 0),
            'evaluated' => (int) ($statusCounts[SubmissionStatus::APPROVED->value] ?? 0),
            'on_revision' => (int) ($statusCounts[SubmissionStatus::REVISIONS_REQUIRED->value] ?? 0),
        ];
    }

    /**
     * One row per organizational unit with a submission on file, so admins can see research
     * output/activity by school or office rather than only in aggregate.
     *
     * @return Collection<int, object{organizational_unit: string, total: int, proposals: int, completed: int, approved: int}>
     */
    public function byOrganizationalUnit(): Collection
    {
        return ResearchSubmission::query()
            ->whereNotNull('organizational_unit')
            ->get(['organizational_unit', 'classification', 'status'])
            ->groupBy('organizational_unit')
            ->map(fn (Collection $submissions, string $unit) => (object) [
                'organizational_unit' => $unit,
                'total' => $submissions->count(),
                'proposals' => $submissions->where('classification', 'proposal')->count(),
                'completed' => $submissions->where('classification', 'completed')->count(),
                'approved' => $submissions->where('status', SubmissionStatus::APPROVED)->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return Collection<string, int> keyed by recommendation value => count
     */
    public function recommendationCounts(): Collection
    {
        return Review::query()
            ->select('recommendation', DB::raw('count(*) as aggregate'))
            ->whereNotNull('recommendation')
            ->groupBy('recommendation')
            ->pluck('aggregate', 'recommendation')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * One row per calendar month for the trailing $months months (including months with
     * zero submissions, so the trend line doesn't silently skip gaps), oldest first.
     *
     * @return Collection<int, object{label: string, count: int}>
     */
    public function submissionTrend(int $months = 12): Collection
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $counts = ResearchSubmission::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (ResearchSubmission $submission) => $submission->created_at->format('Y-m'))
            ->map->count();

        return collect(range(0, $months - 1))->map(function (int $i) use ($start, $counts) {
            $month = $start->copy()->addMonths($i);

            return (object) [
                'label' => $month->format('M Y'),
                'count' => (int) ($counts[$month->format('Y-m')] ?? 0),
            ];
        });
    }

    /**
     * Null when nothing has been approved yet, rather than a misleading 0. Broken into
     * days + hours (not a single decimal-day average) so the reports page can state a
     * concrete "3 days, 6 hrs" instead of an ambiguous "3.3 days".
     *
     * @return array{days: int, hours: int}|null
     */
    public function averageTimeToApproval(): ?array
    {
        $submissions = ResearchSubmission::query()
            ->where('status', SubmissionStatus::APPROVED->value)
            ->whereNotNull('approved_at')
            ->get(['created_at', 'approved_at']);

        if ($submissions->isEmpty()) {
            return null;
        }

        $averageMinutes = (int) round($submissions->avg(fn (ResearchSubmission $submission) => $submission->created_at->diffInMinutes($submission->approved_at)));

        return [
            'days' => intdiv($averageMinutes, 1440),
            'hours' => intdiv($averageMinutes % 1440, 60),
        ];
    }

    /**
     * Avg/median days submissions spend in each status before moving on, reconstructed from
     * the activity log rather than a dedicated status-history table (this app doesn't keep
     * one). Every action below is logged exactly once per genuine transition, *except*
     * 'submission.reviewers_assigned' — that one also fires when reviewers are merely
     * reassigned on a submission that's already under review, so it only counts as an actual
     * draft/submitted->under_review transition when the simulated state matches what
     * AdminSubmissionController::assignReviewer() itself requires (submitted or resubmitted).
     * Replaying each submission's own event history in order (rather than trusting the
     * action name alone) is what keeps that noise out.
     *
     * Only *closed* intervals count — a submission still sitting in its current status
     * hasn't finished that leg yet, and folding an in-progress wait into the average would
     * understate how long the status usually takes once it actually resolves.
     *
     * @return Collection<string, array{avg: float|null, median: float|null, count: int}>
     */
    public function timeInStatus(): Collection
    {
        $submissions = ResearchSubmission::query()->get(['id', 'created_at']);

        $eventsBySubmission = ActivityLog::query()
            ->where('subject_type', ResearchSubmission::class)
            ->whereIn('action', [
                'submission.submitted',
                'submission.reviewers_assigned',
                'submission.revisions_required',
                'submission.resubmitted',
                'submission.approved',
                'submission.promoted_to_completed',
            ])
            ->orderBy('created_at')
            ->get(['subject_id', 'action', 'created_at'])
            ->groupBy('subject_id');

        $daysByStatus = [];

        foreach ($submissions as $submission) {
            $timeline = [['status' => SubmissionStatus::DRAFT->value, 'at' => $submission->created_at]];
            $current = SubmissionStatus::DRAFT->value;

            foreach ($eventsBySubmission->get($submission->id, collect()) as $event) {
                $next = match (true) {
                    $event->action === 'submission.submitted' && $current === SubmissionStatus::DRAFT->value => SubmissionStatus::SUBMITTED->value,
                    $event->action === 'submission.reviewers_assigned' && in_array($current, [SubmissionStatus::SUBMITTED->value, SubmissionStatus::RESUBMITTED->value], true) => SubmissionStatus::UNDER_REVIEW->value,
                    $event->action === 'submission.revisions_required' && $current === SubmissionStatus::UNDER_REVIEW->value => SubmissionStatus::REVISIONS_REQUIRED->value,
                    $event->action === 'submission.resubmitted' && $current === SubmissionStatus::REVISIONS_REQUIRED->value => SubmissionStatus::RESUBMITTED->value,
                    $event->action === 'submission.approved' && $current === SubmissionStatus::UNDER_REVIEW->value => SubmissionStatus::APPROVED->value,
                    // A proposal's approval resets it to draft for the completed-research phase
                    // (see SubmissionDecisionService::evaluate()) rather than ending its timeline.
                    $event->action === 'submission.promoted_to_completed' && $current === SubmissionStatus::UNDER_REVIEW->value => SubmissionStatus::DRAFT->value,
                    default => null,
                };

                if ($next === null) {
                    continue;
                }

                $timeline[] = ['status' => $next, 'at' => $event->created_at];
                $current = $next;
            }

            for ($i = 0; $i < count($timeline) - 1; $i++) {
                $days = $timeline[$i]['at']->diffInMinutes($timeline[$i + 1]['at']) / 1440;
                $daysByStatus[$timeline[$i]['status']][] = $days;
            }
        }

        return collect([
            SubmissionStatus::DRAFT,
            SubmissionStatus::SUBMITTED,
            SubmissionStatus::UNDER_REVIEW,
            SubmissionStatus::REVISIONS_REQUIRED,
            SubmissionStatus::RESUBMITTED,
        ])->mapWithKeys(function (SubmissionStatus $status) use ($daysByStatus) {
            $days = $daysByStatus[$status->value] ?? [];

            if (empty($days)) {
                return [$status->value => ['avg' => null, 'median' => null, 'count' => 0]];
            }

            sort($days);
            $count = count($days);
            $median = $count % 2 === 0
                ? ($days[$count / 2 - 1] + $days[$count / 2]) / 2
                : $days[intdiv($count, 2)];

            return [$status->value => [
                'avg' => round(array_sum($days) / $count, 1),
                'median' => round($median, 1),
                'count' => $count,
            ]];
        });
    }

    /**
     * How many revise-and-resubmit loops submissions typically go through. Unlike
     * timeInStatus(), this doesn't need the state-machine replay — 'submission.revisions_required'
     * is logged exactly once per actual revision decision (SubmissionDecisionService::evaluate()),
     * with no reassignment-style false positives to guard against.
     *
     * @return array{avg: float|null, distribution: array<string, int>}
     */
    public function revisionCycleStats(): array
    {
        $cyclesPerSubmission = ActivityLog::query()
            ->where('subject_type', ResearchSubmission::class)
            ->where('action', 'submission.revisions_required')
            ->selectRaw('subject_id, count(*) as aggregate')
            ->groupBy('subject_id')
            ->pluck('aggregate', 'subject_id');

        // Every submission that has ever been submitted belongs in the denominator, even one
        // approved on its very first pass (zero cycles) — otherwise the average would only
        // reflect submissions that needed at least one revision, skewing it upward.
        $counts = ResearchSubmission::query()
            ->whereNotNull('submitted_at')
            ->pluck('id')
            ->map(fn (int $id) => (int) ($cyclesPerSubmission[$id] ?? 0));

        if ($counts->isEmpty()) {
            return ['avg' => null, 'distribution' => ['0' => 0, '1' => 0, '2' => 0, '3+' => 0]];
        }

        $distribution = $counts->countBy(fn (int $n) => $n >= 3 ? '3+' : (string) $n);

        return [
            'avg' => round($counts->avg(), 1),
            'distribution' => [
                '0' => $distribution['0'] ?? 0,
                '1' => $distribution['1'] ?? 0,
                '2' => $distribution['2'] ?? 0,
                '3+' => $distribution['3+'] ?? 0,
            ],
        ];
    }
}
