<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notice;

class NoticeController extends Controller
{
    public function index()
    {
        $notices = Notice::where('is_published', true)->latest()->paginate(10);
        return response()->json($notices);
    }

    public function show(Notice $notice)
    {
        abort_unless($notice->is_published, 404);
        return response()->json($notice);
    }
}
