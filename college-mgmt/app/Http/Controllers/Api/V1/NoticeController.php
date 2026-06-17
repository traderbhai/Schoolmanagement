<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notice;

class NoticeController extends Controller
{
    public function index()
    {
        $notices = Notice::visibleTo(auth()->user())->latest()->paginate(10);
        return response()->json($notices);
    }

    public function show(Notice $notice)
    {
        abort_unless(Notice::visibleTo(auth()->user())->whereKey($notice->id)->exists(), 404);
        return response()->json($notice);
    }
}
