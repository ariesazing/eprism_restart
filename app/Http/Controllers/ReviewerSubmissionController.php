<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\ResearchDocument;
use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Services\ActivityLogger;
use App\Services\SubmissionDecisionService;
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
        private readonly SubmissionDecisionService $decisions,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $query = $request->user()->assignedSubmissions()->with('researcher');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('research_type')) {
            $query->where('research_type', $type);
        }

        if ($classification = $request->query('classification')) {
            $query->where('classification', $classification);
        }

        return view('reviewer.submissions.index', [
            'submissions' => $query->latest()->get(),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
                'research_type' => $type ?? '',
                'classification' => $classification ?? '',
            ],
        ]);
    }

    public function show(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);

        $submission->load([
            'researcher',
            'proponents',
            'documents.uploader',
            'reviews' => fn ($query) => $query->where('reviewer_id', $request->user()->id),
            'snapshots' => fn ($query) => $query->with('generator')->orderByDesc('version'),
        ]);

        $existingReview = $submission->reviews->first();

        return view('reviewer.submissions.show', [
            'submission' => $submission,
            'template' => $submission->template(),
            'existingReview' => $existingReview,
        ]);
    }

    public function storeReview(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);

        $validated = $request->validate([
            'originality' => ['required', 'integer', 'between:1,5'],
            'methodology' => ['required', 'integer', 'between:1,5'],
            'clarity' => ['required', 'integer', 'between:1,5'],
            'compliance' => ['required', 'integer', 'between:1,5'],
            'comments' => ['required', 'string'],
            'recommendation' => ['required', 'in:approve,minor_revision,major_revision'],
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
            ]
        );

        $submission->update([
            'status' => SubmissionStatus::UNDER_REVIEW,
            'reviewed_at' => now(),
        ]);

        $this->activity->log(
            $request->user(),
            'review.submitted',
            $submission,
            "{$request->user()->name} submitted a \"{$validated['recommendation']}\" evaluation for \"{$submission->title}\" ({$submission->reference_code})."
        );

        $this->decisions->evaluate($submission, $request->user());

        return back()->with('status', 'Evaluation submitted.');
    }

    public function download(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function view(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function manuscript(Request $request, ResearchSubmission $submission): Response
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);

        $snapshot = $submission->latestSnapshot();
        abort_unless($snapshot !== null, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).'.pdf"',
        ]);
    }

    public function manuscriptVersion(Request $request, ResearchSubmission $submission, ResearchSnapshot $snapshot): Response
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).' v'.$snapshot->version.'.pdf"',
        ]);
    }

    public function reviewManuscript(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('reviewer.submissions.manuscript', $submission),
            'commentsUrl' => route('reviewer.submissions.comments.index', $submission),
            'backUrl' => route('reviewer.submissions.show', $submission),
            'canCreate' => true,
            'canEditAll' => false,
        ]);
    }

    public function reviewManuscriptVersion(Request $request, ResearchSubmission $submission, ResearchSnapshot $snapshot): View
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('reviewer.submissions.manuscript.version', [$submission, $snapshot]),
            'commentsUrl' => route('reviewer.submissions.comments.index', $submission, ['snapshot' => $snapshot->id]),
            'backUrl' => route('reviewer.submissions.show', $submission),
            'canCreate' => false,
            'canEditAll' => false,
            'snapshotId' => $snapshot->id,
        ]);
    }
}
