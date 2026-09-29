<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReviewerWorkload;
use Illuminate\Contracts\View\View;

class ReviewerHistoryController extends Controller
{
    public function __invoke(User $reviewer): View
    {
        abort_unless($reviewer->isReviewer(), 404);

        return view('admin.reviewers.history', [
            'reviewer' => $reviewer,
            'activeCount' => $reviewer->assignedSubmissions()->whereIn('research_submissions.status', ReviewerWorkload::ACTIVE_STATUSES)->count(),
            'completedCount' => $reviewer->reviews()->whereNotNull('submitted_at')->count(),
            'researchCount' => $reviewer->reviews()->distinct()->count('research_submission_id'),
            'assignments' => $reviewer->assignedSubmissions()
                ->with(['reviews' => fn ($query) => $query->where('reviewer_id', $reviewer->id)])
                ->orderByPivot('created_at', 'desc')->orderBy('research_submissions.id', 'desc')
                ->paginate(10, ['research_submissions.*'], 'assignments_page')->withQueryString(),
            // User::reviews is deliberately unfiltered: retain earlier manuscript versions
            // and evaluations for submissions from which this reviewer was unassigned.
            'evaluations' => $reviewer->reviews()->with(['submission', 'manuscriptVersion.snapshot'])
                ->orderByDesc('updated_at')->orderByDesc('id')
                ->paginate(15, ['*'], 'evaluations_page')->withQueryString(),
        ]);
    }
}
