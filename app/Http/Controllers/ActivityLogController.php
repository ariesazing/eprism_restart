<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'in:asc,desc'],
        ]);
        $sort = $request->string('sort')->trim()->value() === 'asc' ? 'asc' : 'desc';

        $query = ActivityLog::query()->with('causer')->orderBy('created_at', $sort)->orderBy('id', $sort);

        if ($action = $request->string('action')->trim()->value()) {
            $query->where('action', $action);
        }

        if ($date = $request->string('date')->trim()->value()) {
            $query->whereDate('created_at', $date);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhereHas('causer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        return view('admin.activity.index', [
            'logs' => $query->paginate(15)->onEachSide(2)->withQueryString(),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'sort' => $sort,
        ]);
    }
}
