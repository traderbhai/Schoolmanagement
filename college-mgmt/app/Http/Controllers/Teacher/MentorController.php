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

    private function ensureCanMentor(Student $student): void
    {
        $teacher = $this->teacher();
        abort_unless($teacher && $student->mentor_id === $teacher->user_id, 403);
    }

    private function ensureActiveMentor(Student $student): void
    {
        $teacher = $this->teacher();
        abort_unless(
            $teacher && $teacher->status === 'active' && $student->mentor_id === $teacher->user_id && $student->status === 'active',
            403,
            'Only active mentors can update active mentee workflows.'
        );
    }

    public function index()
    {
        $teacher = $this->teacher();
        if (!$teacher) abort(403);

        $mentees = Student::where('mentor_id', $teacher->user_id)
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

        $upcomingMeetings = MentorMeeting::where('mentor_id', $teacher->user_id)
            ->where('meeting_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->with('student.user')
            ->orderBy('meeting_date')
            ->take(10)
            ->get();

        $canManageMentoring = $teacher->status === 'active';

        return view('teacher.mentor.index', compact('mentees', 'upcomingMeetings', 'canManageMentoring'));
    }

    public function mentee(Student $student)
    {
        $teacher = $this->teacher();
        $this->ensureCanMentor($student);

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
            ->where('mentor_id', $teacher->user_id)
            ->orderByDesc('meeting_date')
            ->get();

        // Attendance by subject
        $attBySubject = Attendance::where('student_id', $student->id)
            ->with('timetableEntry.subject')
            ->get()
            ->filter(fn ($attendance) => $attendance->timetableEntry?->subject)
            ->groupBy(fn ($attendance) => $attendance->timetableEntry->subject_id)
            ->map(function ($records) {
                return (object) [
                    'subject' => $records->first()->timetableEntry->subject,
                    'total' => $records->count(),
                    'present' => $records->where('status', 'present')->count(),
                ];
            })
            ->values();

        // Exam results
        $results = ExamResult::where('student_id', $student->id)
            ->with(['exam.subject'])
            ->latest('id')
            ->take(10)
            ->get();

        $canManageMentoring = $teacher->status === 'active' && $student->status === 'active';

        return view('teacher.mentor.mentee', compact('student', 'messages', 'meetings', 'attBySubject', 'results', 'canManageMentoring'));
    }

    public function sendMessage(Request $request, Student $student)
    {
        $this->ensureActiveMentor($student);

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
        $this->ensureActiveMentor($student);

        $request->validate([
            'meeting_date' => 'required|date|after_or_equal:today',
            'topic'        => 'required|string|max:255',
            'notes'        => 'nullable|string|max:500',
        ]);

        MentorMeeting::create([
            'student_id'   => $student->id,
            'mentor_id'    => $teacher->user_id,
            'meeting_date' => $request->meeting_date,
            'topic'        => $request->topic,
            'notes'        => $request->notes,
            'status'       => 'scheduled',
        ]);

        return back()->with('success', 'Meeting scheduled.');
    }
}
