<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSubmissionController extends Controller
{
    public function index(): View
    {
        return view('admin.submissions.index', [
            'submissions' => ResearchSubmission::query()->with(['researcher', 'reviewer', 'reviews.reviewer', 'documents'])->latest()->get(),
            'reviewers' => User::query()->where('role', UserRole::REVIEWER->value)->where('approval_status', 'approved')->orderBy('name')->get(),
        ]);
    }

    public function assignReviewer(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'reviewer_id' => ['required', 'exists:users,id'],
        ]);

        $reviewer = User::query()->whereKey($validated['reviewer_id'])->firstOrFail();
        abort_unless($reviewer->isReviewer() && $reviewer->isApproved(), 422);

        $submission->update([
            'assigned_reviewer_id' => $reviewer->id,
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);

        return back()->with('status', 'Reviewer assigned.');
    }

    public function approveReview(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'approval_notes' => $validated['approval_notes'] ?? null,
        ]);

        return back()->with('status', 'Reviewer evaluation approved.');
    }

    public function requestRevision(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:2000'],
        ]);

        $submission->update([
            'status' => SubmissionStatus::REVISIONS_REQUIRED,
            'admin_notes' => $validated['admin_notes'],
        ]);

        return back()->with('status', 'Submission returned for revision.');
    }

    public function approveSubmission(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->update([
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return back()->with('status', 'Research approved and published to the repository.');
    }

    public function download(ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function reports(): View
    {
        return view('admin.reports', [
            'submissionsByStatus' => ResearchSubmission::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'reviewerLoads' => User::query()
                ->where('role', UserRole::REVIEWER->value)
                ->withCount('assignedSubmissions')
                ->orderBy('name')
                ->get(),
            'approvedResearch' => ResearchSubmission::query()
                ->with(['researcher', 'reviewer'])
                ->where('status', SubmissionStatus::APPROVED->value)
                ->latest('approved_at')
                ->get(),
        ]);
    }
}