<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\SubmissionStatus;
use App\Models\ActivityLog;
use App\Models\DocumentComment;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\SubmissionReadinessService;
use App\Services\SubmissionStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SubmissionReadinessService $readiness,
        private readonly SubmissionStatisticsService $statistics,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return view('dashboard', ['role' => 'admin', 'data' => $this->adminData()]);
        }

        if ($user->isReviewer()) {
            return view('dashboard', ['role' => 'reviewer', 'data' => $this->reviewerData($user)]);
        }

        if ($user->isResearcher()) {
            return view('dashboard', ['role' => 'researcher', 'data' => $this->researcherData($user)]);
        }

        return view('dashboard', ['role' => null, 'data' => []]);
    }

    private function adminData(): array
    {
        $statusCounts = ResearchSubmission::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // Matches what the repository page actually lists as approved: approved proposals
        // (proposal_approved_at, independent of current status — see RepositoryController)
        // plus completed research (status = approved), not just a raw status tally.
        $approvedTotal = ResearchSubmission::query()->whereNotNull('proposal_approved_at')->count()
            + ResearchSubmission::query()->where('status', SubmissionStatus::APPROVED->value)->count();

        return [
            'summaryStatusCounts' => $statusCounts,
            'summaryTotal' => $statusCounts->sum(),
            'approvedTotal' => $approvedTotal,
            'unassignedCount' => ResearchSubmission::query()
                ->where('status', '!=', SubmissionStatus::DRAFT->value)
                ->whereDoesntHave('reviewers')->count(),
            'categorization' => $this->statistics->categorization(),
            'disabledUsers' => User::query()->where('status', AccountStatus::DISABLED->value)->count(),
            'publishedResearch' => ResearchSubmission::query()->where('status', SubmissionStatus::APPROVED->value)->count(),
            'recentActivity' => ActivityLog::query()->with('causer')->latest('created_at')->take(5)->get(),
            'oversight' => ResearchSubmission::query()
                ->with(['researcher', 'snapshots'])
                ->whereIn('status', [
                    SubmissionStatus::SUBMITTED->value,
                    SubmissionStatus::UNDER_REVIEW->value,
                    SubmissionStatus::REVISIONS_REQUIRED->value,
                    SubmissionStatus::RESUBMITTED->value,
                ])
                ->latest('updated_at')
                ->take(8)
                ->get(),
        ];
    }

    private function reviewerData(User $user): array
    {
        // This is a dashboard preview, not the authoritative queue (that's the paginated
        // /reviewer/submissions page) — capped and column-projected so a reviewer with a
        // long assignment history doesn't pull every column of every submission they've
        // ever been assigned to on every dashboard load. select() must come before
        // withCount() — withCount() only defaults to "select *" when no columns have been
        // set yet, so calling it after an explicit select() lets it append just its count
        // subquery instead of clobbering (or being silently ignored by) our projection.
        $assignments = $user->assignedSubmissions()
            ->select([
                'research_submissions.id',
                'research_submissions.reference_code',
                'research_submissions.title',
                'research_submissions.researcher_id',
                'research_submissions.status',
                'research_submissions.created_at',
            ])
            ->with('researcher:id,name')
            ->withCount('snapshots')
            // Table-qualified — the research_submission_reviewer pivot has its own
            // created_at/status-shaped columns, so bare "status"/"created_at" is genuinely
            // ambiguous once this join is combined with an explicit column list (unlike the
            // old unqualified `select(['*'])` default, which SQLite happened to resolve
            // without complaint).
            ->where('research_submissions.status', '!=', SubmissionStatus::DRAFT->value)
            ->orderByDesc('research_submissions.created_at')
            ->take(20)
            ->get();
        $activeAssignments = $assignments->whereIn('status', [
            SubmissionStatus::SUBMITTED, SubmissionStatus::UNDER_REVIEW, SubmissionStatus::RESUBMITTED,
        ]);

        return [
            'assignedSubmissions' => $activeAssignments->concat($assignments->diff($activeAssignments)),
            'activeAssignmentCount' => $activeAssignments->count(),
            'submittedReviewCount' => $user->assignedReviews()->whereNotNull('submitted_at')->count(),
            'reviews' => $user->assignedReviews()->with('submission')->latest('submitted_at')->take(10)->get(),
            'commentsAuthored' => DocumentComment::query()->where('author_id', $user->id)->count(),
        ];
    }

    private function researcherData(User $user): array
    {
        $submissions = $user->submissions()->with('reviewers')->latest()->get();

        $readiness = $submissions
            ->whereIn('status', [SubmissionStatus::DRAFT, SubmissionStatus::REVISIONS_REQUIRED])
            ->mapWithKeys(fn (ResearchSubmission $submission) => [$submission->id => $this->readiness->assess($submission)]);

        $feedback = DocumentComment::query()
            ->whereHas('submission', fn ($query) => $query->where('researcher_id', $user->id))
            ->visibleToResearcher()
            ->with(['author:id,name', 'submission:id,title,reference_code'])
            ->latest()
            ->take(6)
            ->get();

        $statusCounts = $submissions
            ->groupBy(fn (ResearchSubmission $submission) => $submission->status->value)
            ->map->count();

        $recentActivity = ActivityLog::query()
            ->where('subject_type', ResearchSubmission::class)
            ->whereIn('subject_id', $submissions->pluck('id'))
            ->with('causer:id,name')
            ->latest()
            ->take(6)
            ->get();

        return [
            'submissions' => $submissions,
            'readiness' => $readiness,
            'feedback' => $feedback,
            'statusCounts' => $statusCounts,
            'recentActivity' => $recentActivity,
        ];
    }
}
