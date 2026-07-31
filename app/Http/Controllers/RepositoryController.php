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

        return view('repository.index', [
            'submissions' => $query->latest('approved_at')->get(),
            'ownOnly' => $ownOnly,
        ]);
    }
}