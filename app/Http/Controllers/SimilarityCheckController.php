<?php

namespace App\Http\Controllers;

use App\Jobs\RunSimilarityCheck;
use App\Models\ResearchSubmission;
use App\Models\SimilarityCheck;
use App\Services\ActivityLogger;
use App\Similarity\SimilarityReportBuilder;
use App\Similarity\TextExtractor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The similarity check (see App\Similarity): start a check from the submission page, then watch
 * it and read its report on a dedicated page. Advisory only — nothing here blocks or changes a
 * submission. Researchers run it on their own work; reviewers and admins can run it too (see
 * ResearchSubmission::canRunTextChecks()). A check belongs to whoever requested it: it's their
 * report, their "finished" popup, and their one-at-a-time limit.
 */
class SimilarityCheckController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function store(Request $request, ResearchSubmission $submission, TextExtractor $extractor): RedirectResponse
    {
        abort_unless($submission->canRunTextChecks($request->user()), 403);

        $running = $submission->similarityChecks()
            ->where('requested_by', $request->user()->id)
            ->whereIn('status', [SimilarityCheck::STATUS_QUEUED, SimilarityCheck::STATUS_RUNNING])
            ->latest('id')
            ->first();

        if ($running !== null && ! $running->expireIfStale()) {
            return redirect()->route('submissions.similarity.show', [$submission, $running])
                ->with('status', 'A similarity check is already running for this submission.');
        }

        $minimum = (int) config('similarity.min_document_words');

        if ($extractor->wordCount($extractor->paragraphs($submission)) < $minimum) {
            return back()->withErrors(['similarity' => "Write at least {$minimum} words in your chapters before running a similarity check."]);
        }

        $check = $submission->similarityChecks()->create([
            'requested_by' => $request->user()->id,
            'status' => SimilarityCheck::STATUS_QUEUED,
        ]);

        RunSimilarityCheck::dispatch($check->id);

        $this->activity->log($request->user(), 'submission.similarity_check_started', $submission, "{$request->user()->name} started a similarity check on \"{$submission->title}\" ({$submission->reference_code}).");

        return redirect()->route('submissions.similarity.show', [$submission, $check]);
    }

    public function show(Request $request, ResearchSubmission $submission, SimilarityCheck $check, SimilarityReportBuilder $reports): View
    {
        $this->authorizeCheck($request, $submission, $check);

        $check->expireIfStale();

        // Opening its page is being told: a finished check the researcher has now seen (or its
        // failure message) must not be announced again by the "check finished" popup elsewhere.
        $check->acknowledge();

        return view('researcher.submissions.similarity', [
            'submission' => $submission,
            'check' => $check,
            'report' => $check->isCompleted() ? $reports->build($check) : null,
            'backUrl' => $this->backUrl($request, $submission),
        ]);
    }

    public function status(Request $request, ResearchSubmission $submission, SimilarityCheck $check): JsonResponse
    {
        $this->authorizeCheck($request, $submission, $check);

        $check->expireIfStale();

        return response()->json([
            'status' => $check->status,
            'error' => $check->error,
            // Lets the progress page tell "still working" apart from "nobody has picked this up".
            'queued_for' => $check->queuedForSeconds(),
        ]);
    }

    private function authorizeCheck(Request $request, ResearchSubmission $submission, SimilarityCheck $check): void
    {
        $user = $request->user();

        abort_unless($submission->canRunTextChecks($user), 403);
        abort_unless($check->research_submission_id === $submission->id, 404);
        // Someone else's check on the same submission (a reviewer's, say) isn't the researcher's to
        // read — admins, who oversee every check, are the exception.
        abort_unless($check->requested_by === $user->id || $user->isAdmin(), 403);
    }

    private function backUrl(Request $request, ResearchSubmission $submission): string
    {
        $user = $request->user();

        return match (true) {
            $user->isAdmin() => route('admin.submissions.index'),
            $user->isReviewer() => route('reviewer.submissions.show', $submission),
            default => route('submissions.show', $submission).'#similarity-check',
        };
    }
}
