<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user?->isResearcher()) {
            $scope = 'own';
        } elseif ($user?->isReviewer()) {
            $scope = 'reviewed';
        } else {
            $scope = 'all';
        }

        $search = $request->query('search');
        $type = $request->query('research_type');

        $baseQuery = function () use ($user, $scope, $search, $type) {
            $query = ResearchSubmission::query()->with(['researcher', 'reviewers']);

            if ($scope === 'own') {
                $query->where('researcher_id', $user->id);
            } elseif ($scope === 'reviewed') {
                $query->whereHas('reviewers', fn ($q) => $q->whereKey($user->id));
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('researcher', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
                });
            }

            if ($type) {
                $query->where('research_type', $type);
            }

            return $query;
        };

        // Two durable, independent signals rather than one — a promoted proposal resets
        // its own status back to draft for the completed-research phase (see
        // SubmissionDecisionService), so "approved" can no longer be read off the current
        // status alone once a research has moved past its proposal stage.
        $approvedProposals = $baseQuery()->whereNotNull('proposal_approved_at')->latest('proposal_approved_at')->get();
        $completedResearch = $baseQuery()->where('status', SubmissionStatus::APPROVED->value)->latest('approved_at')->get();

        return view('repository.index', [
            'approvedProposals' => $approvedProposals,
            'completedResearch' => $completedResearch,
            'scope' => $scope,
            'filters' => [
                'search' => $search ?? '',
                'research_type' => $type ?? '',
            ],
        ]);
    }
}
