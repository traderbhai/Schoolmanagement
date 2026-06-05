<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notice;

class NoticeController extends Controller
{
    public function index()
    {
        $notices = Notice::where('is_published', true)
            ->where('publish_date', '<=', now())
            ->where(fn($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()))
            ->where(fn($q) => $q->where('audience', 'all')->orWhere('audience', 'students'))
            ->latest()
            ->paginate(10);

        return view('student.notices', compact('notices'));
    }

    public function show(Notice $notice)
    {
        abort_unless($notice->is_published, 404);
        return view('student.notice-show', compact('notice'));
    }
}
