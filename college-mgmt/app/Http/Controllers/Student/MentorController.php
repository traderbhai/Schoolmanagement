<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{MentorMeeting, MentorMessage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MentorController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $student->load('mentor');
        $meetings = MentorMeeting::where('student_id', $student->id)
            ->orderByDesc('meeting_date')->paginate(10);
        $messages = MentorMessage::where('student_id', $student->id)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        return view('student.mentor.index', compact('student','meetings','messages'));
    }

    public function requestMeeting(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student && $student->mentor_id, 403, 'No mentor assigned.');

        $data = $request->validate([
            'meeting_date' => 'required|date|after:today',
            'topic'        => 'required|string|max:255',
            'notes'        => 'nullable|string|max:1000',
        ]);

        MentorMeeting::create(array_merge($data, [
            'student_id' => $student->id,
            'mentor_id'  => $student->mentor_id,
            'status'     => 'scheduled',
        ]));

        return back()->with('success', 'Meeting request sent to your mentor.');
    }

    public function sendMessage(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student && $student->mentor_id, 403, 'No mentor assigned.');

        $request->validate(['message' => 'required|string|max:2000']);

        MentorMessage::create([
            'student_id' => $student->id,
            'sender_id'  => Auth::id(),
            'message'    => $request->message,
        ]);

        return back()->with('success', 'Message sent.');
    }
}
