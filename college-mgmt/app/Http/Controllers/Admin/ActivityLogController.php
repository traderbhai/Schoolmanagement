<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $r)
    {
        $logs = ActivityLog::with('user')
            ->when($r->action, fn($q) => $q->where('action', $r->action))
            ->when($r->search, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$r->search}%")))
            ->when($r->from, fn($q) => $q->whereDate('created_at', '>=', $r->from))
            ->when($r->to, fn($q) => $q->whereDate('created_at', '<=', $r->to))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity-log.index', compact('logs'));
    }
}
