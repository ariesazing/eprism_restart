<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ResearchConcern;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Notifications\NewUserConcernNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ResearchConcernController extends Controller
{
    public function store(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        $validated = $request->validateWithBag('concern', [
            'category' => ['required', 'string', 'in:general,technical,rubric,deadline,other'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $concern = $submission->concerns()->create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
        ]);

        $this->notifyAdmins($concern);

        return back()->with('status', 'Your concern has been submitted to the administrators.');
    }

    public function storeGlobal(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('concern', [
            'category' => ['required', 'string', 'in:general,technical,rubric,deadline,other'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'research_submission_id' => ['nullable', 'exists:research_submissions,id'],
        ]);

        if (! empty($validated['research_submission_id'])) {
            $linked = ResearchSubmission::findOrFail($validated['research_submission_id']);
            abort_unless($request->user()->isAdmin() || $linked->researcher_id === $request->user()->id || ($request->user()->isReviewer() && $linked->reviewers()->whereKey($request->user()->id)->exists()), 403);
        }

        $concern = ResearchConcern::create([
            'user_id' => $request->user()->id,
            'research_submission_id' => $validated['research_submission_id'] ?? null,
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
        ]);

        $this->notifyAdmins($concern);

        return back()->with('status', 'Your concern has been submitted to the administrators.');
    }

    private function notifyAdmins(ResearchConcern $concern): void
    {
        $admins = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where('status', 'active')
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewUserConcernNotification($concern));
        }
    }
}
