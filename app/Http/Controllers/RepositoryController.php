<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\ResearchSubmission;
use App\Services\RapmRoutingSlipService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RepositoryController extends Controller
{
    public function __construct(
        private readonly RapmRoutingSlipService $routingSlip,
    ) {}

    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'organizational_unit' => ['nullable', 'string', 'max:255'],
            'unit_type' => ['nullable', 'in:school,non_school'],
            'research_type' => ['nullable', 'in:basic,action'],
            'authorship' => ['nullable', 'in:sole,collaborative'],
            'sort' => ['nullable', 'in:newest,oldest'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
        $unit = $request->query('organizational_unit');
        $unitType = $request->query('unit_type');
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
        $authorship = $request->query('authorship');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        if ($dateFrom && $dateTo && Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {
            throw ValidationException::withMessages(['date_to' => 'The end date must be on or after the start date.']);
        }
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'newest';
        $direction = $sort === 'oldest' ? 'asc' : 'desc';

        $baseQuery = function () use ($user, $scope, $search, $type, $authorship, $unit, $unitType) {
            $query = ResearchSubmission::query()->with(['researcher', 'reviewers'])->withCount('proponents');

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

            $query->authorship($authorship);

            $query->when($unit, fn ($q) => $q->where('organizational_unit', $unit));
            $query->when($unitType, fn ($q) => $q->where('organizational_unit_type', $unitType));

            return $query;
        };

        // Two durable, independent signals rather than one — a promoted proposal resets
        // its own status back to draft for the completed-research phase (see
        // SubmissionDecisionService), so "approved" can no longer be read off the current
        // status alone once a research has moved past its proposal stage.
        $approvedProposalsQuery = $baseQuery()->whereNotNull('proposal_approved_at');
        if ($dateFrom) $approvedProposalsQuery->whereDate('proposal_approved_at', '>=', $dateFrom);
        if ($dateTo) $approvedProposalsQuery->whereDate('proposal_approved_at', '<=', $dateTo);
        $approvedProposals = $approvedProposalsQuery
            ->orderBy('proposal_approved_at', $direction)->orderBy('id', $direction)
            ->paginate(10, ['*'], 'proposals_page')
            ->withQueryString();
        $completedResearchQuery = $baseQuery()->where('status', SubmissionStatus::APPROVED->value);
        if ($dateFrom) $completedResearchQuery->whereDate('approved_at', '>=', $dateFrom);
        if ($dateTo) $completedResearchQuery->whereDate('approved_at', '<=', $dateTo);
        $completedResearch = $completedResearchQuery
            ->orderBy('approved_at', $direction)->orderBy('id', $direction)
            ->paginate(10, ['*'], 'completed_page')
            ->withQueryString();

        $completedResearch->each(fn (ResearchSubmission $submission) => $this->routingSlip->ensureGenerated($submission));

        return view('repository.index', [
            'approvedProposals' => $approvedProposals,
            'completedResearch' => $completedResearch,
            'scope' => $scope,
            'schools' => OrganizationalUnit::query()->orderBy('name')->pluck('name'),
            'filters' => [
                'search' => $search ?? '',
                'research_type' => $type ?? '',
                'authorship' => $authorship ?? '',
                'sort' => $sort,
                'organizational_unit' => $unit ?? '',
                'unit_type' => $unitType ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
        ]);
    }
}
