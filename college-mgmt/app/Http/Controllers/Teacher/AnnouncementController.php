<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{SubjectAnnouncement, Subject, Term, TimetableEntry};
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    private function activeTeacher()
    {
        return auth()->user()->teacher;
    }

    private function ensureActiveTeacher(): void
    {
        abort_unless($this->activeTeacher()?->status === 'active', 403, 'Only active teachers can manage announcements.');
    }

    private function teacherSubjectIds(): array
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) return [];
        return TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->pluck('subject_id')->unique()->toArray();
    }

    private function ensureTeachesSubject(int $subjectId): void
    {
        abort_unless(in_array($subjectId, $this->teacherSubjectIds(), true), 403, 'You do not teach this subject.');
    }

    public function index()
    {
        $announcements = SubjectAnnouncement::whereIn('subject_id', $this->teacherSubjectIds())
            ->where('posted_by', auth()->id())
            ->with('subject')
            ->latest()
            ->paginate(20);

        $subjects = Subject::whereIn('id', $this->teacherSubjectIds())->get();
        $canManageAnnouncements = $this->activeTeacher()?->status === 'active';
        return view('teacher.announcements.index', compact('announcements', 'subjects', 'canManageAnnouncements'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title'      => 'required|string|max:255',
            'body'       => 'required|string',
            'is_pinned'  => 'boolean',
        ]);
        $this->ensureActiveTeacher();
        $this->ensureTeachesSubject((int) $request->subject_id);

        $title = trim((string) $request->input('title'));
        $body = trim((string) $request->input('body'));
        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Enter a non-blank announcement title.';
        }

        if ($body === '') {
            $errors['body'] = 'Enter non-blank announcement details.';
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        SubjectAnnouncement::create([
            'subject_id' => $request->subject_id,
            'posted_by'  => auth()->id(),
            'term_id'    => Term::latest('start_date')->first()?->id,
            'title'      => $title,
            'body'       => $body,
            'is_pinned'  => $request->boolean('is_pinned'),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    public function destroy(SubjectAnnouncement $announcement)
    {
        $this->ensureActiveTeacher();
        if ($announcement->posted_by !== auth()->id()) abort(403);
        $announcement->delete();
        return back()->with('success', 'Announcement archived. Teaching history was preserved.');
    }
}
