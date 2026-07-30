<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Services\SubmissionSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewerSubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionSnapshotService $snapshots,
    ) {}

    public function index(Request $request): View
    {
        return view('reviewer.submissions.index', [
            'submissions' => ResearchSubmission::query()
                ->with('researcher')
                ->where('assigned_reviewer_id', $request->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->assigned_reviewer_id === $request->user()->id, 403);

        $submission->load([
            'researcher',
            'proponents',
            'documents.uploader',
            'reviews' => fn ($query) => $query->where('reviewer_id', $request->user()->id),
        ]);

        $existingReview = $submission->reviews->first();

        return view('reviewer.submissions.show', [
            'submission' => $submission,
            'template' => $submission->template(),
            'existingReview' => $existingReview,
            'locked' => $existingReview?->isApproved() ?? false,
        ]);
    }

    public function storeReview(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->assigned_reviewer_id === $request->user()->id, 403);

        $existingReview = $submission->reviews()->where('reviewer_id', $request->user()->id)->first();
        abort_unless($existingReview === null || ! $existingReview->isApproved(), 403);

        $validated = $request->validate([
            'originality' => ['required', 'integer', 'between:1,5'],
            'methodology' => ['required', 'integer', 'between:1,5'],
            'clarity' => ['required', 'integer', 'between:1,5'],
            'compliance' => ['required', 'integer', 'between:1,5'],
            'comments' => ['required', 'string'],
            'recommendation' => ['required', 'in:approve,minor_revision,major_revision,reject'],
        ]);

        $submission->reviews()->updateOrCreate(
            ['reviewer_id' => $request->user()->id],
            [
                'criteria_scores' => [
                    'originality' => (int) $validated['originality'],
                    'methodology' => (int) $validated['methodology'],
                    'clarity' => (int) $validated['clarity'],
                    'compliance' => (int) $validated['compliance'],
                ],
                'comments' => $validated['comments'],
                'recommendation' => $validated['recommendation'],
                'submitted_at' => now(),
                'approved_at' => null,
                'approved_by' => null,
                'approval_notes' => null,
            ]
        );

        $submission->update([
            'status' => SubmissionStatus::UNDER_REVIEW,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Evaluation submitted for administrator approval.');
    }

    public function download(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->assigned_reviewer_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function view(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->assigned_reviewer_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function manuscript(Request $request, ResearchSubmission $submission): Response
    {
        abort_unless($submission->assigned_reviewer_id === $request->user()->id, 403);

        $snapshot = $submission->latestSnapshot();
        abort_unless($snapshot !== null, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).'.pdf"',
        ]);
    }

    public function reviewManuscript(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->assigned_reviewer_id === $request->user()->id, 403);

        $existingReview = $submission->reviews()->where('reviewer_id', $request->user()->id)->first();

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('reviewer.submissions.manuscript', $submission),
            'commentsUrl' => route('reviewer.submissions.comments.index', $submission),
            'backUrl' => route('reviewer.submissions.show', $submission),
            'canCreate' => ! ($existingReview?->isApproved() ?? false),
            'canEditAll' => false,
        ]);
    }
}
