<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::query()->with('causer')->latest('created_at');

        if ($action = $request->string('action')->trim()->value()) {
            $query->where('action', $action);
        }

        if ($date = $request->string('date')->trim()->value()) {
            $query->whereDate('created_at', $date);
        }

        return view('admin.activity.index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
