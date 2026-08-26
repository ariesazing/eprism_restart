<?php

namespace App\Http\Controllers;

use App\Models\SubmissionWindow;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'windows' => ['required', 'array'],
            'windows.*.is_open' => ['required', 'boolean'],
            'windows.*.opens_at' => ['nullable', 'date'],
            'windows.*.closes_at' => ['nullable', 'date', 'after_or_equal:windows.*.opens_at'],
        ])['windows'];

        $changed = 0;

        foreach (self::CLASSIFICATIONS as $classification) {
            if (! isset($payload[$classification])) {
                continue;
            }

            $attributes = $payload[$classification];
            $window = SubmissionWindow::forClassification($classification);

            $window->fill([
                'is_open' => (bool) $attributes['is_open'],
                'opens_at' => $attributes['opens_at'] !== null ? now()->parse($attributes['opens_at'])->startOfDay() : null,
                'closes_at' => $attributes['closes_at'] !== null ? now()->parse($attributes['closes_at'])->endOfDay() : null,
            ]);

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
}
