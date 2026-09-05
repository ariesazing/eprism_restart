<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Models\RapmDocument;
use App\Models\ResearchDocument;
use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Services\ActivityLogger;
use App\Services\SubmissionDecisionService;
use App\Services\SubmissionSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        $query = $request->user()->assignedSubmissions()->with('researcher')->where('status', '!=', SubmissionStatus::DRAFT->value);

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
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);

        $submission->load([
            'researcher',
            'proponents',
            'documents.uploader',
            'reviews' => fn ($query) => $query->with('reviewer'),
            'snapshots' => fn ($query) => $query->with('generator')->orderByDesc('version'),
        ]);

        $existingReview = $submission->reviews->firstWhere('reviewer_id', $request->user()->id);
        $reviewSummary = $submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);

        // Blind until you submit: a reviewer only sees peers' evaluations once their own
        // is in, so an early look never anchors their own scoring.
        $peerReviews = $existingReview?->submitted_at
            ? $submission->reviews
                ->where('reviewer_id', '!=', $request->user()->id)
                ->whereNotNull('submitted_at')
            : collect();

        return view('reviewer.submissions.show', [
            'submission' => $submission,
            'template' => $submission->template(),
            'existingReview' => $existingReview,
            'peerReviews' => $peerReviews,
            'reviewSummary' => ($reviewSummary?->outcome === RapmDocument::OUTCOME_APPROVED) ? $reviewSummary : null,
        ]);
    }

    public function storeReview(Request $request, ResearchSubmission $submission): RedirectResponse|JsonResponse
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);
        abort_unless($submission->status !== SubmissionStatus::APPROVED, 403);

        $rules = ['comments' => ['required', 'string'], 'recommendation' => ['required', 'in:approve,minor_revision,major_revision']];

        foreach (ResearchEvaluationRubric::criteriaKeys() as $criterion) {
            $rules[$criterion] = ['required', Rule::in(ResearchEvaluationRubric::tierKeysFor($criterion))];
        }

        $validated = $request->validate($rules);

        $tierSelections = collect($validated)->only(ResearchEvaluationRubric::criteriaKeys())->all();
        $scoredCriteria = ResearchEvaluationRubric::scoreFromTiers($tierSelections);
        $totalScore = ResearchEvaluationRubric::totalScore($scoredCriteria);

        // The pro forma this rubric is drawn from is explicit: a paper needs at least
        // PASSING_SCORE to be accepted, so "approve" isn't a free choice below that —
        // the reviewer's own scoring has to actually support the recommendation.
        if ($validated['recommendation'] === 'approve' && $totalScore < ResearchEvaluationRubric::PASSING_SCORE) {
            $message = "This paper's total score of {$totalScore}/".ResearchEvaluationRubric::MAX_SCORE.' is below the required '.ResearchEvaluationRubric::PASSING_SCORE.' needed to approve — select a revision recommendation instead.';

            // Submit-with-feedback modal (reviewer/submissions/show.blade.php) needs a
            // non-2xx response to tell this apart from success — a plain back()/302
            // would otherwise look identical to a successful redirect to fetch().
            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['recommendation' => [$message]]], 422);
            }

            return back()->withErrors(['recommendation' => $message])->withInput();
        }

        $submission->reviews()->updateOrCreate(
            ['reviewer_id' => $request->user()->id],
            [
                'criteria_scores' => $scoredCriteria,
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

        // A unanimous approval on a 'proposal' classification promotes the submission and
        // detaches every reviewer (see SubmissionDecisionService::evaluate()) — including
        // the reviewer whose own request just triggered it. back() would otherwise redirect
        // straight into show()'s reviewer-pivot check with a now-stale membership, 403ing
        // the very reviewer who just approved.
        if (! $submission->reviewers()->whereKey($request->user()->id)->exists()) {
            $message = 'Evaluation submitted — this submission was approved and promoted to completed research. It has been removed from your queue and will need to be reassigned before further review.';

            if ($request->wantsJson()) {
                return response()->json(['redirect' => route('reviewer.submissions.index'), 'message' => $message]);
            }

            return redirect()->route('reviewer.submissions.index')->with('status', $message);
        }

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('reviewer.submissions.show', $submission), 'message' => 'Evaluation submitted.']);
        }

        return back()->with('status', 'Evaluation submitted.');
    }

    public function download(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function view(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function manuscript(Request $request, ResearchSubmission $submission): Response
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);

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
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).' v'.$snapshot->version.'.pdf"',
        ]);
    }

    public function reviewManuscript(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('reviewer.submissions.manuscript', $submission),
            'commentsUrl' => route('reviewer.submissions.comments.index', $submission),
            'backUrl' => route('reviewer.submissions.show', $submission),
            'canCreate' => true,
            'canEditAll' => false,
            // Pin the live view to the snapshot it was actually rendered against — without
            // this, wireEcho()'s snapshot guard in pdf-review.js is skipped entirely (its
            // ctx.snapshotId is null), so a comment broadcast for a newer snapshot (e.g.
            // after the researcher resubmits while this page is still open) would render
            // unconditionally on top of the still-displayed, now-stale pages.
            'snapshotId' => $submission->latestSnapshot()?->id,
        ]);
    }

    public function reviewManuscriptVersion(Request $request, ResearchSubmission $submission, ResearchSnapshot $snapshot): View
    {
        abort_unless($submission->reviewers()->whereKey($request->user()->id)->exists(), 403);
        abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);
        abort_unless($snapshot->research_submission_id === $submission->id, 404);

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('reviewer.submissions.manuscript.version', [$submission, $snapshot]),
            'commentsUrl' => route('reviewer.submissions.comments.index', [$submission, 'snapshot' => $snapshot->id]),
            'backUrl' => route('reviewer.submissions.show', $submission),
            'canCreate' => false,
            'canEditAll' => false,
            'snapshotId' => $snapshot->id,
        ]);
    }
}
