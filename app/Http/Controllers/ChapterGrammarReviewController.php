<?php

namespace App\Http\Controllers;

use App\Models\ResearchSubmission;
use App\Models\SubmissionSection;
use App\Services\ChapterGrammarReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Check grammar" review on the chapters page for chapters edited in ONLYOFFICE, which can't
 * underline grammar as you type — see ChapterGrammarReview for why and how. Reviewers and admins
 * get the same read-only review from their own routes (they can't edit a chapter, but can check it).
 */
class ChapterGrammarReviewController extends Controller
{
    public function __invoke(Request $request, ResearchSubmission $submission, SubmissionSection $section, ChapterGrammarReview $review): JsonResponse
    {
        abort_unless($submission->canRunTextChecks($request->user()), 403);
        abort_unless($section->research_submission_id === $submission->id, 404);
        abort_unless($section->type === 'rich_text', 404);
        // The whole-manuscript engine has no per-chapter documents to review.
        abort_if($submission->usesManuscript(), 404);

        return response()->json($review->review($section));
    }
}
