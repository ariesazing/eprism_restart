<?php

namespace App\Http\Controllers;

use App\Models\SubmissionWindow;
use App\Services\ActivityLogger;
use App\Services\SubmissionWindowNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionWindowController extends Controller
{
    private const RESEARCH_TYPES = ['basic', 'action'];

    private const CLASSIFICATIONS = ['proposal', 'completed'];

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.submission-timeline.index', [
            'windows' => collect(self::RESEARCH_TYPES)->mapWithKeys(fn (string $researchType) => [
                $researchType => collect(self::CLASSIFICATIONS)->mapWithKeys(fn (string $classification) => [
                    $classification => SubmissionWindow::forWindow($researchType, $classification),
                ]),
            ]),
        ]);
    }

    /**
     * Basic/Action research and Proposal/Completed research each have their own
     * independent is_open switch — any of the four can be open, closed, or scheduled
     * without affecting the other three.
     */
    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'windows' => ['required', 'array'],
            'windows.*.*.is_open' => ['nullable', 'boolean'],
            'windows.*.*.opens_at' => ['nullable', 'date'],
            'windows.*.*.closes_at' => ['nullable', 'date', 'after_or_equal:windows.*.*.opens_at'],
            'windows.*.*.memorandum' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'windows.*.*.remove_memorandum' => ['nullable', 'boolean'],
        ]);

        $changed = 0;

        foreach (self::RESEARCH_TYPES as $researchType) {
            foreach (self::CLASSIFICATIONS as $classification) {
                $attributes = $payload['windows'][$researchType][$classification] ?? [];
                $window = SubmissionWindow::forWindow($researchType, $classification);

                $notifier = app(SubmissionWindowNotifier::class);
                $notifier->check($window);
                $window->refresh();

                $window->fill([
                    'is_open' => filter_var($attributes['is_open'] ?? false, FILTER_VALIDATE_BOOL),
                    'opens_at' => ($attributes['opens_at'] ?? null) !== null ? now()->parse($attributes['opens_at'])->startOfDay() : null,
                    'closes_at' => ($attributes['closes_at'] ?? null) !== null ? now()->parse($attributes['closes_at'])->endOfDay() : null,
                ]);

                $memorandum = $request->file("windows.{$researchType}.{$classification}.memorandum");
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
                    $notifier->check($window);
                    $changed++;
                }
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
    public function memorandum(string $researchType, string $classification): StreamedResponse
    {
        abort_unless(in_array($researchType, self::RESEARCH_TYPES, true), 404);
        abort_unless(in_array($classification, self::CLASSIFICATIONS, true), 404);

        $window = SubmissionWindow::forWindow($researchType, $classification);
        abort_unless($window->memorandum_path, 404);

        return Storage::disk('local')->response($window->memorandum_path, $window->memorandum_original_name);
    }
}
