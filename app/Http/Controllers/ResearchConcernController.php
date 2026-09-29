<?php

namespace App\Http\Controllers;

use App\Models\ResearchConcern;
use App\Models\ResearchSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResearchConcernController extends Controller
{
    public function store(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:general,technical,rubric,deadline,other'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $submission->concerns()->create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
        ]);

        return back()->with('status', 'Your concern has been submitted to the administrators.');
    }
}
