<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use Illuminate\Contracts\View\View;

class RepositoryController extends Controller
{
    public function index(): View
    {
        return view('repository.index', [
            'submissions' => ResearchSubmission::query()
                ->with(['researcher', 'reviewer'])
                ->where('status', SubmissionStatus::APPROVED->value)
                ->latest('approved_at')
                ->get(),
        ]);
    }
}