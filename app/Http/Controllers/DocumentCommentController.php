<?php

namespace App\Http\Controllers;

use App\Evaluation\ResearchEvaluationRubric;
use App\Events\DocumentCommentBroadcast;
use App\Models\DocumentComment;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentCommentController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request, ResearchSubmission $submission): JsonResponse
    {
        $validated = $request->validate([
            'snapshot' => ['nullable', 'integer'],
        ]);

        return response()->json($this->visibleComments($request->user(), $submission, $validated['snapshot'] ?? null));
    }

    /**
     * Scoped to one manuscript version at a time — a comment's highlight is a set of
     * page-relative coordinates measured against the PDF layout current when it was made,
     * so a comment made against an older snapshot renders in the wrong place (or over
     * unrelated text) once the proponent revises and a new snapshot is generated. Callers
     * that don't ask for a specific version see only the current snapshot's comments;
     * older ones stay reachable by passing that snapshot's id (see the version-history UI).
     */
    private function visibleComments(User $user, ResearchSubmission $submission, ?int $snapshotId = null)
    {
        $query = $submission->comments()->with(['author:id,name', 'lastEditor:id,name'])->orderBy('page_number');

        if ($user->isAdmin()) {
            // Full access.
        } elseif ($user->isReviewer()) {
            abort_unless($submission->reviewers()->whereKey($user->id)->exists(), 403);
        } elseif ($user->isResearcher()) {
            abort_unless($submission->researcher_id === $user->id, 403);
            $query->visibleToResearcher();
        } else {
            abort(403);
        }

        if ($snapshotId !== null) {
            abort_unless($submission->snapshots()->whereKey($snapshotId)->exists(), 404);
            $query->where('research_snapshot_id', $snapshotId);
        } elseif ($latest = $submission->latestSnapshot()) {
            $query->where('research_snapshot_id', $latest->id);
        }

        return $query->get();
    }

    public function store(Request $request, ResearchSubmission $submission): JsonResponse
    {
        $review = $this->resolveMutableReview($request->user(), $submission);

        $validated = $request->validate([
            'page_number' => ['required', 'integer', 'min:1'],
            'quote_text' => ['nullable', 'string'],
            'anchor' => ['required', 'array'],
            'body' => ['required', 'string'],
        ]);

        $comment = $submission->comments()->create([
            'research_snapshot_id' => $submission->latestSnapshot()?->id,
            'review_id' => $review->id,
            'author_id' => $request->user()->id,
            'page_number' => $validated['page_number'],
            'quote_text' => $validated['quote_text'] ?? null,
            'anchor' => $validated['anchor'],
            'body' => $validated['body'],
        ]);

        $comment->load(['author:id,name', 'lastEditor:id,name']);

        $this->broadcastSafely(new DocumentCommentBroadcast($comment, 'created'));

        $this->activity->log($request->user(), 'comment.created', $submission, "{$request->user()->name} left a comment on \"{$submission->title}\" (p.{$comment->page_number}).");

        return response()->json($comment, 201);
    }

    public function update(Request $request, ResearchSubmission $submission, DocumentComment $comment): JsonResponse
    {
        $this->authorizeMutation($request->user(), $submission, $comment);

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $comment->update([
            'body' => $validated['body'],
            'last_edited_by' => $request->user()->id,
        ]);

        $comment->load(['author:id,name', 'lastEditor:id,name']);

        $this->broadcastSafely(new DocumentCommentBroadcast($comment, 'updated'));

        $this->activity->log($request->user(), 'comment.updated', $submission, "{$request->user()->name} edited a comment on \"{$submission->title}\" (p.{$comment->page_number}).");

        return response()->json($comment);
    }

    public function destroy(Request $request, ResearchSubmission $submission, DocumentComment $comment): JsonResponse
    {
        $this->authorizeMutation($request->user(), $submission, $comment);

        $pageNumber = $comment->page_number;

        $comment->delete();

        $this->broadcastSafely(new DocumentCommentBroadcast($comment, 'deleted'));

        $this->activity->log($request->user(), 'comment.deleted', $submission, "{$request->user()->name} deleted a comment on \"{$submission->title}\" (p.{$pageNumber}).");

        return response()->json(['deleted' => true]);
    }

    /**
     * Broadcasting runs inline (QUEUE_CONNECTION=sync), so if the socket server is
     * unreachable this would otherwise throw and 500 the response *after* the comment
     * mutation already committed — the client would see a failed request for a change
     * that actually saved, and only notice it was there on their next full page load.
     * Real-time delivery to other viewers is a nice-to-have on top of an already-persisted
     * change, not a condition for the request itself succeeding.
     */
    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast document comment event', ['exception' => $e]);
        }
    }

    private function resolveMutableReview(User $user, ResearchSubmission $submission): Review
    {
        if ($user->isReviewer()) {
            abort_unless($submission->reviewers()->whereKey($user->id)->exists(), 403);

            return $submission->reviews()->firstOrCreate(
                ['reviewer_id' => $user->id],
                [
                    'criteria_scores' => ResearchEvaluationRubric::scoreFromTiers(
                        collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all()
                    ),
                    'comments' => '',
                    'recommendation' => 'minor_revision',
                ]
            );
        }

        abort(403);
    }

    private function authorizeMutation(User $user, ResearchSubmission $submission, DocumentComment $comment): void
    {
        abort_unless($comment->research_submission_id === $submission->id, 404);

        if ($user->isReviewer()) {
            abort_unless($submission->reviewers()->whereKey($user->id)->exists(), 403);
            abort_unless($comment->author_id === $user->id, 403);

            return;
        }

        abort(403);
    }
}
