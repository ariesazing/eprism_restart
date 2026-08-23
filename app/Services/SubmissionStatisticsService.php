<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
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
     * Null when nothing has been approved yet, rather than a misleading 0.
     */
    public function averageDaysToApproval(): ?float
    {
        $submissions = ResearchSubmission::query()
            ->where('status', SubmissionStatus::APPROVED->value)
            ->whereNotNull('approved_at')
            ->get(['created_at', 'approved_at']);

        if ($submissions->isEmpty()) {
            return null;
        }

        return round($submissions->avg(fn (ResearchSubmission $submission) => $submission->created_at->diffInDays($submission->approved_at)), 1);
    }
}
