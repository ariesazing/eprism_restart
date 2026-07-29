<?php

namespace App\Http\Controllers;

use App\Events\DocumentCommentBroadcast;
use App\Models\DocumentComment;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCommentController extends Controller
{
    public function index(Request $request, ResearchSubmission $submission, ResearchDocument $document): JsonResponse
    {
        abort_unless($document->research_submission_id === $submission->id, 404);

        $user = $request->user();
        $query = $document->comments()->with(['author:id,name', 'lastEditor:id,name'])->orderBy('page_number');

        if ($user->isAdmin()) {
            // Full access.
        } elseif ($user->isReviewer()) {
            abort_unless($submission->assigned_reviewer_id === $user->id, 403);
        } elseif ($user->isResearcher()) {
            abort_unless($submission->researcher_id === $user->id, 403);
            $query->whereHas('review', fn ($q) => $q->whereNotNull('approved_at'));
        } else {
            abort(403);
        }

        return response()->json($query->get());
    }

    public function store(Request $request, ResearchSubmission $submission, ResearchDocument $document): JsonResponse
    {
        abort_unless($document->research_submission_id === $submission->id, 404);

        $review = $this->resolveMutableReview($request->user(), $submission);

        $validated = $request->validate([
            'page_number' => ['required', 'integer', 'min:1'],
            'quote_text' => ['nullable', 'string'],
            'anchor' => ['required', 'array'],
            'body' => ['required', 'string'],
        ]);

        $comment = $document->comments()->create([
            'review_id' => $review->id,
            'author_id' => $request->user()->id,
            'page_number' => $validated['page_number'],
            'quote_text' => $validated['quote_text'] ?? null,
            'anchor' => $validated['anchor'],
            'body' => $validated['body'],
        ]);

        $comment->load(['author:id,name', 'lastEditor:id,name']);

        broadcast(new DocumentCommentBroadcast($comment, 'created'))->toOthers();

        return response()->json($comment, 201);
    }

    public function update(Request $request, ResearchSubmission $submission, ResearchDocument $document, DocumentComment $comment): JsonResponse
    {
        $this->authorizeMutation($request->user(), $submission, $document, $comment);

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $comment->update([
            'body' => $validated['body'],
            'last_edited_by' => $request->user()->id,
        ]);

        $comment->load(['author:id,name', 'lastEditor:id,name']);

        broadcast(new DocumentCommentBroadcast($comment, 'updated'))->toOthers();

        return response()->json($comment);
    }

    public function destroy(Request $request, ResearchSubmission $submission, ResearchDocument $document, DocumentComment $comment): JsonResponse
    {
        $this->authorizeMutation($request->user(), $submission, $document, $comment);

        $comment->delete();

        broadcast(new DocumentCommentBroadcast($comment, 'deleted'))->toOthers();

        return response()->json(['deleted' => true]);
    }

    private function resolveMutableReview(User $user, ResearchSubmission $submission): Review
    {
        if ($user->isReviewer()) {
            abort_unless($submission->assigned_reviewer_id === $user->id, 403);

            $review = $submission->reviews()->firstOrCreate(
                ['reviewer_id' => $user->id],
                [
                    'criteria_scores' => ['originality' => 3, 'methodology' => 3, 'clarity' => 3, 'compliance' => 3],
                    'comments' => '',
                    'recommendation' => 'minor_revision',
                ]
            );

            abort_unless(! $review->isApproved(), 403);

            return $review;
        }

        if ($user->isAdmin()) {
            $review = $submission->reviews()->where('reviewer_id', $submission->assigned_reviewer_id)->latest()->first();
            abort_unless($review !== null, 422);

            return $review;
        }

        abort(403);
    }

    private function authorizeMutation(User $user, ResearchSubmission $submission, ResearchDocument $document, DocumentComment $comment): void
    {
        abort_unless($document->research_submission_id === $submission->id, 404);
        abort_unless($comment->research_document_id === $document->id, 404);

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isReviewer()) {
            abort_unless($submission->assigned_reviewer_id === $user->id, 403);
            abort_unless($comment->author_id === $user->id, 403);
            abort_unless(! $comment->review->isApproved(), 403);

            return;
        }

        abort(403);
    }
}
