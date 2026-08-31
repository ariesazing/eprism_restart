<?php

namespace App\Http\Controllers;

use App\Models\SubmissionWindow;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionWindowController extends Controller
{
    private const CLASSIFICATIONS = ['proposal', 'completed'];

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.submission-timeline.index', [
            'windows' => collect(self::CLASSIFICATIONS)
                ->mapWithKeys(fn (string $classification) => [$classification => SubmissionWindow::forClassification($classification)]),
        ]);
    }

    /**
     * Proposal and completed-research submissions are mutually exclusive — only one can
     * be open at a time — so "which is open" is modeled as a single choice (or "none")
     * rather than two independent toggles that could both end up on. Each classification
     * still keeps its own optional date range, since scheduling is orthogonal to which
     * one is currently accepting submissions.
     */
    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'open_classification' => ['required', Rule::in([...self::CLASSIFICATIONS, 'none'])],
            'windows' => ['required', 'array'],
            'windows.*.opens_at' => ['nullable', 'date'],
            'windows.*.closes_at' => ['nullable', 'date', 'after_or_equal:windows.*.opens_at'],
            'windows.*.memorandum' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'windows.*.remove_memorandum' => ['nullable', 'boolean'],
        ]);

        $openClassification = $payload['open_classification'];
        $changed = 0;

        foreach (self::CLASSIFICATIONS as $classification) {
            $attributes = $payload['windows'][$classification] ?? [];
            $window = SubmissionWindow::forClassification($classification);

            $window->fill([
                'is_open' => $openClassification === $classification,
                'opens_at' => ($attributes['opens_at'] ?? null) !== null ? now()->parse($attributes['opens_at'])->startOfDay() : null,
                'closes_at' => ($attributes['closes_at'] ?? null) !== null ? now()->parse($attributes['closes_at'])->endOfDay() : null,
            ]);

            $memorandum = $request->file("windows.{$classification}.memorandum");
            $removeMemorandum = filter_var($attributes['remove_memorandum'] ?? false, FILTER_VALIDATE_BOOL);

            if ($memorandum) {
                if ($window->memorandum_path) {
                    Storage::disk('local')->delete($window->memorandum_path);
                }

                $window->memorandum_path = $memorandum->store('submission-memoranda');
                $window->memorandum_original_name = $memorandum->getClientOriginalName();
            } elseif ($removeMemorandum && $window->memorandum_path) {
                Storage::disk('local')->delete($window->memorandum_path);
                $window->memorandum_path = null;
                $window->memorandum_original_name = null;
            }

            if ($window->isDirty()) {
                $window->updated_by = $request->user()->id;
                $window->save();
                $changed++;
            }
        }

        if ($changed > 0) {
            $this->activity->log(
                $request->user(),
                'submission-window.updated',
                null,
                "{$request->user()->name} updated the submission timeline."
            );
        }

        return back()->with('status', $changed > 0 ? 'Submission timeline updated.' : 'No changes to save.');
    }

    /**
     * Public — the memorandum is meant for prospective researchers browsing the guest
     * welcome page, not just admins, so this intentionally sits outside the admin route
     * group / role middleware.
     */
    public function memorandum(string $classification): StreamedResponse
    {
        abort_unless(in_array($classification, self::CLASSIFICATIONS, true), 404);

        $window = SubmissionWindow::forClassification($classification);
        abort_unless($window->memorandum_path, 404);

        return Storage::disk('local')->response($window->memorandum_path, $window->memorandum_original_name);
    }
}
