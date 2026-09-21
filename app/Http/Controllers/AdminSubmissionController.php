<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Events\SubmissionActivity;
use App\Models\ResearchDocument;
use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RapmRoutingSlipService;
use App\Services\SubmissionSnapshotService;
use App\Services\SubmissionStatisticsService;
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
        private readonly SubmissionStatisticsService $statistics,
        private readonly RapmRoutingSlipService $routingSlip,
    ) {}

    public function index(Request $request): View
    {
        $query = ResearchSubmission::query()
            ->with([
                'researcher',
                'reviewers',
                'reviews' => fn ($query) => $query->whereNotNull('submitted_at')->with('reviewer'),
                'documents',
                'sections',
                // Only the signed-in admin's own checks — a similarity check belongs to whoever ran it.
                'similarityChecks' => fn ($query) => $query->where('requested_by', $request->user()->id),
                'snapshots' => fn ($query) => $query->orderByDesc('version'),
            ])
            ->where('status', '!=', SubmissionStatus::DRAFT->value);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%")
                    ->orWhereHas('researcher', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
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

        if ($reviewer = $request->query('reviewer')) {
            if ($reviewer === 'unassigned') {
                $query->whereDoesntHave('reviewers');
            } else {
                $query->whereHas('reviewers', fn ($rq) => $rq->whereKey($reviewer));
            }
        }

        $sort = $request->string('sort')->trim()->value() === 'asc' ? 'asc' : 'desc';

        $submissions = $query->orderBy('submitted_at', $sort)->paginate(15)->withQueryString();
        $submissions->getCollection()
            ->filter(fn (ResearchSubmission $submission) => $submission->status === SubmissionStatus::APPROVED)
            ->each(fn (ResearchSubmission $submission) => $this->routingSlip->ensureGenerated($submission));

        return view('admin.submissions.index', [
            'submissions' => $submissions,
            'reviewers' => User::query()->where('role', UserRole::REVIEWER->value)->where('status', 'active')->orderBy('name')->get(),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
                'research_type' => $type ?? '',
                'classification' => $classification ?? '',
                'reviewer' => $reviewer ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    public function assignReviewer(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['distinct', 'exists:users,id'],
        ]);

        $reviewers = User::query()->whereKey($validated['reviewer_ids'])->get();
        abort_unless($reviewers->every(fn (User $reviewer) => $reviewer->isReviewer() && $reviewer->isActive()), 422);

        $sync = $submission->reviewers()->sync($reviewers->pluck('id'));

        if (in_array($submission->status, [SubmissionStatus::SUBMITTED, SubmissionStatus::RESUBMITTED], true)) {
            $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);
        }

        // Notify both the newly (and still) assigned reviewers and anyone just removed —
        // both dashboards need to drop or pick up this submission live.
        $affectedReviewerIds = array_unique(array_merge($reviewers->pluck('id')->all(), $sync['detached']));
        event(new SubmissionActivity($submission, 'reviewers_assigned', $affectedReviewerIds));

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

        return $this->manuscriptResponse($submission, $snapshot, $submission->title);
    }

    public function manuscriptVersion(ResearchSubmission $submission, ResearchSnapshot $snapshot): Response
    {
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return $this->manuscriptResponse($submission, $snapshot, $submission->title.' v'.$snapshot->version);
    }

    /**
     * A snapshot's file is encrypted at rest and only ever decrypted in memory per-request
     * (SubmissionSnapshotService::decryptedBytes()) — there's deliberately no on-disk or
     * database cache of the plaintext bytes, so this can't reuse Storage::response()'s
     * BinaryFileResponse the way plain file downloads elsewhere in this controller do. What
     * it can cheaply avoid is repeat full downloads/decrypts of the *same* view: a snapshot
     * is immutable once generated (a new version gets a new id), so its id+version alone is
     * a safe, stable ETag — a repeat open in the same browser session 304s without this
     * controller touching the encrypted file at all.
     */
    private function manuscriptResponse(ResearchSubmission $submission, ResearchSnapshot $snapshot, string $filename): Response
    {
        $etag = '"snapshot-'.$snapshot->id.'-v'.$snapshot->version.'"';

        if (request()->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'.pdf"',
            'ETag' => $etag,
            'Cache-Control' => 'private, max-age=3600, must-revalidate',
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
            // See ReviewerSubmissionController::reviewManuscript() — pins the live view to
            // the snapshot it was rendered against so pdf-review.js's Echo snapshot guard
            // isn't silently skipped.
            'snapshotId' => $submission->latestSnapshot()?->id,
        ]);
    }

    public function reviewManuscriptVersion(ResearchSubmission $submission, ResearchSnapshot $snapshot): View
    {
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('admin.submissions.manuscript.version', [$submission, $snapshot]),
            'commentsUrl' => route('admin.submissions.comments.index', [$submission, 'snapshot' => $snapshot->id]),
            'backUrl' => route('admin.submissions.index'),
            'canCreate' => false,
            'canEditAll' => false,
            'snapshotId' => $snapshot->id,
        ]);
    }

    public function reports(Request $request): View
    {
        $reviewerLoads = User::query()
            ->where('role', UserRole::REVIEWER->value)
            ->withCount('assignedSubmissions')
            ->orderBy('name');

        if ($reviewerSearch = $request->query('reviewer_search')) {
            $reviewerLoads->where('name', 'like', "%{$reviewerSearch}%");
        }

        $approvedResearch = ResearchSubmission::query()
            ->with(['researcher', 'reviewers'])
            ->where('status', SubmissionStatus::APPROVED->value);

        if ($search = $request->query('search')) {
            $approvedResearch->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('researcher', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->query('research_type')) {
            $approvedResearch->where('research_type', $type);
        }

        if ($classification = $request->query('classification')) {
            $approvedResearch->where('classification', $classification);
        }

        return view('admin.reports', [
            'submissionsByStatus' => ResearchSubmission::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'categorization' => $this->statistics->categorization(),
            'stages' => $this->statistics->stages(),
            'submissionTrend' => $this->statistics->submissionTrend(),
            'byOrganizationalUnit' => $this->statistics->byOrganizationalUnit(),
            'recommendationCounts' => $this->statistics->recommendationCounts(),
            'avgTimeToApproval' => $this->statistics->averageTimeToApproval(),
            'timeInStatus' => $this->statistics->timeInStatus(),
            'revisionCycles' => $this->statistics->revisionCycleStats(),
            'reviewerLoads' => $reviewerLoads->paginate(10, ['*'], 'reviewers_page')->withQueryString(),
            'approvedResearch' => $approvedResearch->latest('approved_at')->paginate(6, ['*'], 'approved_page')->withQueryString(),
            'filters' => [
                'reviewer_search' => $reviewerSearch ?? '',
                'search' => $search ?? '',
                'research_type' => $type ?? '',
                'classification' => $classification ?? '',
            ],
        ]);
    }
}
