<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{AttendanceCondonation, Subject, Attendance};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceCondonationController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $condonations = AttendanceCondonation::with('subject')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')->paginate(15);
        return view('student.condonation.index', compact('condonations'));
    }

    public function create() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        // Only subjects with < 75% attendance
        $attendances = Attendance::with('timetableEntry.subject')
            ->where('student_id', $student->id)->get()
            ->groupBy(fn($a) => $a->timetableEntry?->subject_id);

        $lowSubjects = [];
        foreach ($attendances as $subjectId => $records) {
            if (!$subjectId) continue;
            $total   = $records->count();
            $present = $records->whereIn('status',['present','late'])->count();
            $pct     = $total > 0 ? round(($present / $total) * 100) : 0;
            if ($pct < 75) {
                $subject = $records->first()->timetableEntry?->subject;
                if ($subject) $lowSubjects[] = ['subject' => $subject, 'pct' => $pct, 'deficit' => $total - $present];
            }
        }

        return view('student.condonation.create', compact('lowSubjects'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $data = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'reason'     => 'required|string|max:1000',
        ]);

        AttendanceCondonation::create([
            'student_id' => $student->id,
            'subject_id' => $data['subject_id'],
            'reason'     => $data['reason'],
            'status'     => 'pending',
        ]);

        return redirect()->route('student.condonation.index')
            ->with('success', 'Condonation request submitted.');
    }
}
