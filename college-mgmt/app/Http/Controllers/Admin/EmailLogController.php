<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user() && AccessControl::canViewEmailLogs($request->user()), 403);

        $query = EmailLog::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('to_email', 'like', '%' . $request->search . '%')
                  ->orWhere('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('mailable_class', 'like', '%' . $request->search . '%');
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.email-logs.index', compact('logs'));
    }
}
