<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkNoticeEmail;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index()
    {
        $notices = Notice::with('user')->latest()->paginate(15);
        return view('admin.notices.index', compact('notices'));
    }

    public function create()
    {
        return view('admin.notices.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'audience'     => 'required|in:all,students,teachers,admin',
            'publish_date' => 'required|date',
            'expiry_date'  => 'nullable|date|after:publish_date',
            'is_published' => 'boolean',
        ]);
        $data['user_id'] = auth()->id();
        $notice = Notice::create($data);
        SendBulkNoticeEmail::dispatch($notice);
        return redirect()->route('admin.notices.index')->with('success', 'Notice published.');
    }

    public function show(Notice $notice)
    {
        return view('admin.notices.show', compact('notice'));
    }

    public function edit(Notice $notice)
    {
        return view('admin.notices.edit', compact('notice'));
    }

    public function update(Request $request, Notice $notice)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'audience'     => 'required|in:all,students,teachers,admin',
            'publish_date' => 'required|date',
            'expiry_date'  => 'nullable|date|after:publish_date',
            'is_published' => 'boolean',
        ]);
        $notice->update($data);
        return redirect()->route('admin.notices.index')->with('success', 'Notice updated.');
    }

    public function destroy(Notice $notice)
    {
        $notice->delete();
        return redirect()->route('admin.notices.index')->with('success', 'Notice deleted.');
    }
}
