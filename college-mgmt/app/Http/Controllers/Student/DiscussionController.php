<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Enrollment, Subject, SubjectDiscussion, SubjectDiscussionReply, Student, StudentSubjectEnrollment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscussionController extends Controller {

    private function student(): Student {
        return Student::where('user_id', Auth::id())->firstOrFail();
    }

    public function index(Subject $subject) {
        $student = $this->student();
        $this->ensureEnrolled($student, $subject);
        $canParticipate = $student->status === 'active';

        $discussions = SubjectDiscussion::where('subject_id', $subject->id)
            ->withCount('replies')
            ->with('author')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(20);

        return view('student.discussions.index', compact('subject', 'discussions', 'canParticipate'));
    }

    public function show(Subject $subject, SubjectDiscussion $discussion) {
        abort_if($discussion->subject_id !== $subject->id, 404);
        $student = $this->student();
        $this->ensureEnrolled($student, $subject);
        $canParticipate = $student->status === 'active';

        $discussion->increment('views');
        $discussion->load(['author', 'replies.author']);

        return view('student.discussions.show', compact('subject', 'discussion', 'canParticipate'));
    }

    public function store(Request $request, Subject $subject) {
        $student = $this->student();
        $termId = $this->ensureEnrolled($student, $subject);
        abort_unless($student->status === 'active', 403, 'Discussion posting is available only for active students.');

        $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string|max:5000',
        ]);

        $discussion = SubjectDiscussion::create([
            'subject_id' => $subject->id,
            'posted_by'  => Auth::id(),
            'term_id'    => $termId,
            'title'      => $request->title,
            'body'       => $request->body,
        ]);

        return redirect()->route('student.discussions.show', [$subject, $discussion])
            ->with('success', 'Question posted.');
    }

    public function reply(Request $request, Subject $subject, SubjectDiscussion $discussion) {
        abort_if($discussion->subject_id !== $subject->id, 404);
        $student = $this->student();
        $this->ensureEnrolled($student, $subject);
        abort_unless($student->status === 'active', 403, 'Discussion replies are available only for active students.');

        $request->validate(['body' => 'required|string|max:3000']);

        SubjectDiscussionReply::create([
            'discussion_id' => $discussion->id,
            'posted_by'     => Auth::id(),
            'body'          => $request->body,
        ]);

        return back()->with('success', 'Reply posted.');
    }

    public function markResolved(Subject $subject, SubjectDiscussion $discussion) {
        abort_if($discussion->subject_id !== $subject->id, 404);
        $student = $this->student();
        $this->ensureEnrolled($student, $subject);
        abort_unless($student->status === 'active', 403, 'Discussion updates are available only for active students.');
        abort_if($discussion->posted_by !== Auth::id(), 403);
        $discussion->update(['is_resolved' => true]);
        return back()->with('success', 'Marked as resolved.');
    }

    private function ensureEnrolled(Student $student, Subject $subject): ?int
    {
        $termId = $this->activeEnrollmentTermId($student, $subject);
        abort_if($termId === false, 403, 'Not enrolled in this subject.');

        return $termId;
    }

    private function activeEnrollmentTermId(Student $student, Subject $subject): int|null|false
    {
        $canonical = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN term_id = ? THEN 0 ELSE 1 END', [$student->current_term_id ?? 0])
            ->first(['term_id']);

        if ($canonical) {
            return $canonical->term_id;
        }

        $legacy = Enrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->first(['term_id']);

        return $legacy ? $legacy->term_id : false;
    }
}
