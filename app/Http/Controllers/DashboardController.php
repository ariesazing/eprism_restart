<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $stats = [
            'my_submissions' => 0,
            'assigned_reviews' => 0,
            'pending_users' => 0,
            'pending_review_approvals' => 0,
            'approved_research' => ResearchSubmission::query()
                ->where('status', SubmissionStatus::APPROVED->value)
                ->count(),
        ];

        $recentSubmissions = collect();

        if ($user->isResearcher()) {
            $stats['my_submissions'] = $user->submissions()->count();
            $recentSubmissions = $user->submissions()->latest()->take(5)->get();
        }

        if ($user->isReviewer()) {
            $stats['assigned_reviews'] = $user->assignedSubmissions()->count();
            $recentSubmissions = $user->assignedSubmissions()->latest()->take(5)->get();
        }

        if ($user->isAdmin()) {
            $stats['pending_users'] = User::query()
                ->where('approval_status', ApprovalStatus::PENDING->value)
                ->count();
            $stats['pending_review_approvals'] = Review::query()
                ->whereNotNull('submitted_at')
                ->whereNull('approved_at')
                ->count();
            $recentSubmissions = ResearchSubmission::query()->with(['researcher', 'reviewer'])->latest()->take(5)->get();
        }

        return view('dashboard', [
            'stats' => $stats,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }
}