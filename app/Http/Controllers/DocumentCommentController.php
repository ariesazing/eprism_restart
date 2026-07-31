<?php

namespace App\Http\Controllers;

use App\Events\DocumentCommentBroadcast;
use App\Models\DocumentComment;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCommentController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request, ResearchSubmission $submission): JsonResponse
    {
        return response()->json($this->visibleComments($request->user(), $submission));
    }

    private function visibleComments(User $user, ResearchSubmission $submission)
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
            'review_id' => $review->id,
            'author_id' => $request->user()->id,
            'page_number' => $validated['page_number'],
            'quote_text' => $validated['quote_text'] ?? null,
            'anchor' => $validated['anchor'],
            'body' => $validated['body'],
        ]);

        $comment->load(['author:id,name', 'lastEditor:id,name']);

        broadcast(new DocumentCommentBroadcast($comment, 'created'))->toOthers();

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

        broadcast(new DocumentCommentBroadcast($comment, 'updated'))->toOthers();

        $this->activity->log($request->user(), 'comment.updated', $submission, "{$request->user()->name} edited a comment on \"{$submission->title}\" (p.{$comment->page_number}).");

        return response()->json($comment);
    }

    public function destroy(Request $request, ResearchSubmission $submission, DocumentComment $comment): JsonResponse
    {
        $this->authorizeMutation($request->user(), $submission, $comment);

        $pageNumber = $comment->page_number;

        $comment->delete();

        broadcast(new DocumentCommentBroadcast($comment, 'deleted'))->toOthers();

        $this->activity->log($request->user(), 'comment.deleted', $submission, "{$request->user()->name} deleted a comment on \"{$submission->title}\" (p.{$pageNumber}).");

        return response()->json(['deleted' => true]);
    }

    private function resolveMutableReview(User $user, ResearchSubmission $submission): Review
    {
        if ($user->isReviewer()) {
            abort_unless($submission->reviewers()->whereKey($user->id)->exists(), 403);

            return $submission->reviews()->firstOrCreate(
                ['reviewer_id' => $user->id],
                [
                    'criteria_scores' => ['originality' => 3, 'methodology' => 3, 'clarity' => 3, 'compliance' => 3],
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
