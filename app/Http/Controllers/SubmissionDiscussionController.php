<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Events\SubmissionDiscussionBroadcast;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDiscussionMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmissionDiscussionController extends Controller
{
    public function index(Request $request, ResearchSubmission $submission): JsonResponse
    {
        $this->authorizeAccess($request->user(), $submission);

        $messages = $submission->discussionMessages()
            ->with('author:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request, ResearchSubmission $submission): JsonResponse
    {
        $this->authorizeAccess($request->user(), $submission);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = $submission->discussionMessages()->create([
            'author_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $message->load('author:id,name');

        $this->broadcastSafely(new SubmissionDiscussionBroadcast($message, 'created'));

        return response()->json($message, 201);
    }

    public function destroy(Request $request, ResearchSubmission $submission, SubmissionDiscussionMessage $message): JsonResponse
    {
        $this->authorizeAccess($request->user(), $submission);

        abort_unless($message->research_submission_id === $submission->id, 404);
        abort_unless($request->user()->isAdmin() || $message->author_id === $request->user()->id, 403);

        $message->delete();

        $this->broadcastSafely(new SubmissionDiscussionBroadcast($message, 'deleted'));

        return response()->json(['deleted' => true]);
    }

    /**
     * Reviewer/admin only — this mirrors DocumentCommentController's reviewer gate
     * (assigned + not a draft), but deliberately has no researcher branch at all, same
     * as the broadcast channel authorization in routes/channels.php.
     */
    private function authorizeAccess(User $user, ResearchSubmission $submission): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isReviewer()) {
            abort_unless($submission->reviewers()->whereKey($user->id)->exists(), 403);
            abort_unless($submission->status !== SubmissionStatus::DRAFT, 403);

            return;
        }

        abort(403);
    }

    /**
     * Same rationale as DocumentCommentController::broadcastSafely() — broadcasting is
     * best-effort on top of an already-persisted change, not a condition for the request
     * itself succeeding.
     */
    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast submission discussion event', ['exception' => $e]);
        }
    }
}
