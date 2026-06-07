<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{SubjectAnnouncement, Subject, Term, TimetableEntry};
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    private function teacherSubjectIds(): array
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) return [];
        return TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->pluck('subject_id')->unique()->toArray();
    }

    public function index()
    {
        $announcements = SubjectAnnouncement::whereIn('subject_id', $this->teacherSubjectIds())
            ->where('posted_by', auth()->id())
            ->with('subject')
            ->latest()
            ->paginate(20);

        $subjects = Subject::whereIn('id', $this->teacherSubjectIds())->get();
        return view('teacher.announcements.index', compact('announcements', 'subjects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title'      => 'required|string|max:255',
            'body'       => 'required|string',
            'is_pinned'  => 'boolean',
        ]);

        SubjectAnnouncement::create([
            'subject_id' => $request->subject_id,
            'posted_by'  => auth()->id(),
            'term_id'    => Term::latest('start_date')->first()?->id,
            'title'      => $request->title,
            'body'       => $request->body,
            'is_pinned'  => $request->boolean('is_pinned'),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    public function destroy(SubjectAnnouncement $announcement)
    {
        if ($announcement->posted_by !== auth()->id()) abort(403);
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }
}
