<?php

namespace App\Http\Controllers;

use App\Models\SubmissionWindow;
use Illuminate\Contracts\View\View;

class WelcomeController extends Controller
{
    private const CLASSIFICATIONS = ['proposal', 'completed'];

    public function index(): View
    {
        return view('welcome', [
            'windows' => collect(self::CLASSIFICATIONS)
                ->mapWithKeys(fn (string $classification) => [$classification => SubmissionWindow::forClassification($classification)]),
        ]);
    }
}
