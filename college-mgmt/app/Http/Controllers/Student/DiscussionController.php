<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Subject, SubjectDiscussion, SubjectDiscussionReply, Student, StudentSubjectEnrollment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscussionController extends Controller {

    private function student(): Student {
        return Student::where('user_id', Auth::id())->firstOrFail();
    }

    public function index(Subject $subject) {
        $student = $this->student();
        // Verify enrollment
        $enrolled = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)->exists();
        abort_unless($enrolled, 403, 'Not enrolled in this subject.');

        $discussions = SubjectDiscussion::where('subject_id', $subject->id)
            ->withCount('replies')
            ->with('author')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(20);

        return view('student.discussions.index', compact('subject', 'discussions'));
    }

    public function show(Subject $subject, SubjectDiscussion $discussion) {
        abort_if($discussion->subject_id !== $subject->id, 404);
        $student = $this->student();
        $enrolled = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)->exists();
        abort_unless($enrolled, 403);

        $discussion->increment('views');
        $discussion->load(['author', 'replies.author']);

        return view('student.discussions.show', compact('subject', 'discussion'));
    }

    public function store(Request $request, Subject $subject) {
        $student = $this->student();
        $enrolled = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)->exists();
        abort_unless($enrolled, 403);

        $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string|max:5000',
        ]);

        $discussion = SubjectDiscussion::create([
            'subject_id' => $subject->id,
            'posted_by'  => Auth::id(),
            'term_id'    => $student->current_term_id,
            'title'      => $request->title,
            'body'       => $request->body,
        ]);

        return redirect()->route('student.discussions.show', [$subject, $discussion])
            ->with('success', 'Question posted.');
    }

    public function reply(Request $request, Subject $subject, SubjectDiscussion $discussion) {
        abort_if($discussion->subject_id !== $subject->id, 404);
        $student = $this->student();
        $enrolled = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)->exists();
        abort_unless($enrolled, 403);

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
        abort_if($discussion->posted_by !== Auth::id(), 403);
        $discussion->update(['is_resolved' => true]);
        return back()->with('success', 'Marked as resolved.');
    }
}
