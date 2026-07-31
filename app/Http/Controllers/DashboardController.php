<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\SubmissionStatus;
use App\Models\ActivityLog;
use App\Models\DocumentComment;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\SubmissionReadinessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SubmissionReadinessService $readiness,
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
        $categorization = ResearchSubmission::query()
            ->select('research_type', 'classification', DB::raw('count(*) as aggregate'))
            ->groupBy('research_type', 'classification')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->research_type}:{$row->classification}" => $row->aggregate]);

        $statusCounts = ResearchSubmission::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stages = [
            'submitted' => (int) ($statusCounts[SubmissionStatus::SUBMITTED->value] ?? 0) + (int) ($statusCounts[SubmissionStatus::RESUBMITTED->value] ?? 0),
            'on_evaluation' => (int) ($statusCounts[SubmissionStatus::UNDER_REVIEW->value] ?? 0),
            'evaluated' => (int) ($statusCounts[SubmissionStatus::APPROVED->value] ?? 0),
            'on_revision' => (int) ($statusCounts[SubmissionStatus::REVISIONS_REQUIRED->value] ?? 0),
        ];

        return [
            'categorization' => $categorization,
            'stages' => $stages,
            'pendingUsers' => User::query()->where('approval_status', ApprovalStatus::PENDING->value)->count(),
            'publishedResearch' => ResearchSubmission::query()->where('status', SubmissionStatus::APPROVED->value)->count(),
            'recentActivity' => ActivityLog::query()->with('causer')->latest('created_at')->take(8)->get(),
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
        return [
            'assignedSubmissions' => $user->assignedSubmissions()->with('researcher')->withCount('snapshots')->latest()->get(),
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

        return [
            'submissions' => $submissions,
            'readiness' => $readiness,
            'feedback' => $feedback,
        ];
    }
}
