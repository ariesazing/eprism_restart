<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchDocument;
use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\ActivityLogger;
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
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.submissions.index', [
            'submissions' => ResearchSubmission::query()
                ->with([
                    'researcher',
                    'reviewers',
                    'reviews' => fn ($query) => $query->whereNotNull('submitted_at')->with('reviewer'),
                    'documents',
                ])
                ->where('status', '!=', SubmissionStatus::DRAFT->value)
                ->latest()
                ->get(),
            'reviewers' => User::query()->where('role', UserRole::REVIEWER->value)->where('approval_status', 'approved')->orderBy('name')->get(),
        ]);
    }

    public function assignReviewer(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'reviewer_ids' => ['required', 'array', 'min:3'],
            'reviewer_ids.*' => ['distinct', 'exists:users,id'],
        ]);

        $reviewers = User::query()->whereKey($validated['reviewer_ids'])->get();
        abort_unless($reviewers->every(fn (User $reviewer) => $reviewer->isReviewer() && $reviewer->isApproved()), 422);

        $submission->reviewers()->sync($reviewers->pluck('id'));

        if (in_array($submission->status, [SubmissionStatus::SUBMITTED, SubmissionStatus::RESUBMITTED], true)) {
            $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);
        }

        $this->activity->log(
            $request->user(),
            'submission.reviewers_assigned',
            $submission,
            "{$request->user()->name} assigned ".$reviewers->pluck('name')->join(', ')." to review \"{$submission->title}\" ({$submission->reference_code})."
        );

        return back()->with('status', 'Reviewers assigned.');
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

    public function manuscriptVersion(ResearchSubmission $submission, ResearchSnapshot $snapshot): Response
    {
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).' v'.$snapshot->version.'.pdf"',
        ]);
    }

    public function reviewManuscript(ResearchSubmission $submission): View
    {
        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('admin.submissions.manuscript', $submission),
            'commentsUrl' => route('admin.submissions.comments.index', $submission),
            'backUrl' => route('admin.submissions.index'),
            'canCreate' => false,
            'canEditAll' => false,
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
                ->with(['researcher', 'reviewers'])
                ->where('status', SubmissionStatus::APPROVED->value)
                ->latest('approved_at')
                ->get(),
        ]);
    }
}
