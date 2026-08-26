<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->string('sort')->trim()->value() === 'asc' ? 'asc' : 'desc';

        $query = ActivityLog::query()->with('causer')->orderBy('created_at', $sort);

        if ($action = $request->string('action')->trim()->value()) {
            $query->where('action', $action);
        }

        if ($date = $request->string('date')->trim()->value()) {
            $query->whereDate('created_at', $date);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        if ($user = $request->string('user')->trim()->value()) {
            $query->whereHas('causer', fn ($q) => $q->where('name', 'like', "%{$user}%"));
        }

        return view('admin.activity.index', [
            'logs' => $query->paginate(15)->onEachSide(2)->withQueryString(),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'sort' => $sort,
        ]);
    }
}
