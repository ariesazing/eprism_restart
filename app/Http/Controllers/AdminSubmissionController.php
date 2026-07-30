<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use App\Services\SubmissionSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionSnapshotService $snapshots,
    ) {}

    public function index(): View
    {
        return view('admin.submissions.index', [
            'submissions' => ResearchSubmission::query()
                ->with([
                    'researcher',
                    'reviewer',
                    'reviews' => fn ($query) => $query->whereNotNull('submitted_at')->with('reviewer'),
                    'documents',
                ])
                ->latest()
                ->get(),
            'reviewers' => User::query()->where('role', UserRole::REVIEWER->value)->where('approval_status', 'approved')->orderBy('name')->get(),
        ]);
    }

    public function assignReviewer(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'reviewer_id' => ['required', 'exists:users,id'],
        ]);

        $reviewer = User::query()->whereKey($validated['reviewer_id'])->firstOrFail();
        abort_unless($reviewer->isReviewer() && $reviewer->isApproved(), 422);

        $submission->update([
            'assigned_reviewer_id' => $reviewer->id,
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);

        return back()->with('status', 'Reviewer assigned.');
    }

    public function approveReview(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'approval_notes' => $validated['approval_notes'] ?? null,
        ]);

        return back()->with('status', 'Reviewer evaluation approved.');
    }

    public function updateReview(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'originality' => ['required', 'integer', 'between:1,5'],
            'methodology' => ['required', 'integer', 'between:1,5'],
            'clarity' => ['required', 'integer', 'between:1,5'],
            'compliance' => ['required', 'integer', 'between:1,5'],
            'comments' => ['required', 'string'],
            'recommendation' => ['required', 'in:approve,minor_revision,major_revision,reject'],
        ]);

        $review->update([
            'criteria_scores' => [
                'originality' => (int) $validated['originality'],
                'methodology' => (int) $validated['methodology'],
                'clarity' => (int) $validated['clarity'],
                'compliance' => (int) $validated['compliance'],
            ],
            'comments' => $validated['comments'],
            'recommendation' => $validated['recommendation'],
        ]);

        return back()->with('status', 'Reviewer evaluation updated.');
    }

    public function reopenReview(Review $review): RedirectResponse
    {
        $review->update([
            'approved_at' => null,
            'approved_by' => null,
            'approval_notes' => null,
        ]);

        return back()->with('status', 'Evaluation reopened for reviewer edits.');
    }

    public function requestRevision(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:2000'],
        ]);

        $submission->update([
            'status' => SubmissionStatus::REVISIONS_REQUIRED,
            'admin_notes' => $validated['admin_notes'],
        ]);

        return back()->with('status', 'Submission returned for revision.');
    }

    public function approveSubmission(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->update([
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return back()->with('status', 'Research approved and published to the repository.');
    }

    public function download(ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function view(ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function manuscript(ResearchSubmission $submission): Response
    {
        $snapshot = $submission->latestSnapshot();
        abort_unless($snapshot !== null, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).'.pdf"',
        ]);
    }

    public function reviewManuscript(ResearchSubmission $submission): View
    {
        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('admin.submissions.manuscript', $submission),
            'commentsUrl' => route('admin.submissions.comments.index', $submission),
            'backUrl' => route('admin.submissions.index'),
            'canCreate' => true,
            'canEditAll' => true,
        ]);
    }

    public function reports(): View
    {
        return view('admin.reports', [
            'submissionsByStatus' => ResearchSubmission::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'reviewerLoads' => User::query()
                ->where('role', UserRole::REVIEWER->value)
                ->withCount('assignedSubmissions')
                ->orderBy('name')
                ->get(),
            'approvedResearch' => ResearchSubmission::query()
                ->with(['researcher', 'reviewer'])
                ->where('status', SubmissionStatus::APPROVED->value)
                ->latest('approved_at')
                ->get(),
        ]);
    }
}
