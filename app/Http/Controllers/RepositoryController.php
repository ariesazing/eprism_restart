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
        $ownOnly = $user && $user->isResearcher();

        $query = ResearchSubmission::query()
            ->with(['researcher', 'reviewers'])
            ->where('status', SubmissionStatus::APPROVED->value);

        if ($ownOnly) {
            $query->where('researcher_id', $user->id);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('researcher', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->query('research_type')) {
            $query->where('research_type', $type);
        }

        if ($classification = $request->query('classification')) {
            $query->where('classification', $classification);
        }

        return view('repository.index', [
            'submissions' => $query->latest('approved_at')->get(),
            'ownOnly' => $ownOnly,
            'filters' => [
                'search' => $search ?? '',
                'research_type' => $type ?? '',
                'classification' => $classification ?? '',
            ],
        ]);
    }
}
