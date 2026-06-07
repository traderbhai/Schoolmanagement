<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Student, MentorMessage, MentorMeeting, Attendance, ExamResult, FeeDemand};
use Illuminate\Http\Request;

class MentorController extends Controller
{
    private function teacher()
    {
        return auth()->user()->teacher;
    }

    public function index()
    {
        $teacher = $this->teacher();
        if (!$teacher) abort(403);

        $mentees = Student::where('mentor_id', $teacher->id)
            ->where('status', 'active')
            ->with(['user', 'program', 'batch'])
            ->get()
            ->map(function ($student) {
                // Attendance % overall
                $total   = Attendance::where('student_id', $student->id)->count();
                $present = Attendance::where('student_id', $student->id)->where('status','present')->count();
                $student->att_pct = $total > 0 ? round($present / $total * 100, 1) : null;

                // Unread messages
                $student->unread = MentorMessage::where('student_id', $student->id)
                    ->where('sender_id', $student->user_id)
                    ->whereNull('read_at')->count();

                return $student;
            });

        $upcomingMeetings = MentorMeeting::where('mentor_id', $teacher->id)
            ->where('meeting_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->with('student.user')
            ->orderBy('meeting_date')
            ->take(10)
            ->get();

        return view('teacher.mentor.index', compact('mentees', 'upcomingMeetings'));
    }

    public function mentee(Student $student)
    {
        $teacher = $this->teacher();
        if (!$teacher || $student->mentor_id !== $teacher->id) abort(403);

        $student->load(['user', 'program', 'batch']);

        // Messages thread
        $messages = MentorMessage::where('student_id', $student->id)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        // Mark unread as read
        MentorMessage::where('student_id', $student->id)
            ->where('sender_id', $student->user_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Meetings
        $meetings = MentorMeeting::where('student_id', $student->id)
            ->where('mentor_id', $teacher->id)
            ->orderByDesc('meeting_date')
            ->get();

        // Attendance by subject
        $attBySubject = Attendance::where('student_id', $student->id)
            ->selectRaw('subject_id, COUNT(*) as total, SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present')
            ->groupBy('subject_id')
            ->with('subject')
            ->get();

        // Exam results
        $results = ExamResult::where('student_id', $student->id)
            ->with(['exam.subject'])
            ->latest('id')
            ->take(10)
            ->get();

        return view('teacher.mentor.mentee', compact('student', 'messages', 'meetings', 'attBySubject', 'results'));
    }

    public function sendMessage(Request $request, Student $student)
    {
        $teacher = $this->teacher();
        if (!$teacher || $student->mentor_id !== $teacher->id) abort(403);

        $request->validate(['message' => 'required|string|max:1000']);

        MentorMessage::create([
            'student_id' => $student->id,
            'sender_id'  => auth()->id(),
            'message'    => $request->message,
        ]);

        return back()->with('success', 'Message sent.');
    }

    public function scheduleMeeting(Request $request, Student $student)
    {
        $teacher = $this->teacher();
        if (!$teacher || $student->mentor_id !== $teacher->id) abort(403);

        $request->validate([
            'meeting_date' => 'required|date|after_or_equal:today',
            'topic'        => 'required|string|max:255',
            'notes'        => 'nullable|string|max:500',
        ]);

        MentorMeeting::create([
            'student_id'   => $student->id,
            'mentor_id'    => $teacher->id,
            'meeting_date' => $request->meeting_date,
            'topic'        => $request->topic,
            'notes'        => $request->notes,
            'status'       => 'scheduled',
        ]);

        return back()->with('success', 'Meeting scheduled.');
    }
}
