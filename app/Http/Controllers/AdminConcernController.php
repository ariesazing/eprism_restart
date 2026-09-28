<?php

namespace App\Http\Controllers;

use App\Models\ResearchConcern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminConcernController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = ResearchConcern::with(['submission', 'user', 'responder'])
            ->latest('id');

        if ($status && in_array($status, ['open', 'in_progress', 'resolved'], true)) {
            $query->where('status', $status);
        }

        $concerns = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => ResearchConcern::count(),
            'open' => ResearchConcern::where('status', 'open')->count(),
            'in_progress' => ResearchConcern::where('status', 'in_progress')->count(),
            'resolved' => ResearchConcern::where('status', 'resolved')->count(),
        ];

        return view('admin.concerns.index', [
            'concerns' => $concerns,
            'counts' => $counts,
            'currentStatus' => $status,
        ]);
    }

    public function update(Request $request, ResearchConcern $concern): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved'],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ]);

        $concern->update([
            'status' => $validated['status'],
            'admin_response' => $validated['admin_response'],
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
        ]);

        return back()->with('status', 'Concern updated successfully.');
    }
}
