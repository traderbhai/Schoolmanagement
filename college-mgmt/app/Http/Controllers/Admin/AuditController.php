<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('actor')->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->actor_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->get()
            ->pluck('action');

        return view('admin.audit.index', compact('logs', 'actions'));
    }

    public function show(AuditLog $log)
    {
        return view('admin.audit.show', compact('log'));
    }

    public function search(Request $request)
    {
        $query = AuditLog::with('actor')->latest();

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->whereRaw('changes LIKE ?', [$search])
                ->orWhereHas('actor', fn($q) => $q->where('name', 'like', $search));
        }

        $logs = $query->paginate(50)->withQueryString();
        $actions = [];

        return view('admin.audit.index', compact('logs', 'actions'));
    }
}
