<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Attendance, Semester, Subject, TimetableSlot};
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');

        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $currentSemester = Semester::current() ?? $semesters->first();
        $semesterId = $request->semester_id ?? optional($currentSemester)->id;

        $report = [];
        if ($semesterId) {
            $attendances = Attendance::with(['timetableEntry.subject', 'timetableEntry.slot'])
                ->where('student_id', $student->id)
                ->whereHas('timetableEntry', fn($q) => $q->where('semester_id', $semesterId))
                ->get();

            $grouped = $attendances->groupBy(fn($a) => $a->timetableEntry->subject->name ?? 'Unknown');
            foreach ($grouped as $subjectName => $records) {
                $total   = $records->count();
                $present = $records->whereIn('status', ['present', 'late'])->count();
                $absent  = $records->where('status', 'absent')->count();
                $late    = $records->where('status', 'late')->count();
                $pct     = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                $report[] = [
                    'subject'  => $subjectName,
                    'total'    => $total,
                    'present'  => $present,
                    'absent'   => $absent,
                    'late'     => $late,
                    'pct'      => $pct,
                    'low'      => $pct < 75,
                ];
            }
        }

        return view('student.attendance', compact('semesters', 'semesterId', 'report', 'currentSemester'));
    }

    public function sessions(Subject $subject, Request $request)
    {
        $student = auth()->user()->student;
        abort_unless($student, 403);

        $semesterId = $request->semester_id;

        $query = Attendance::with(['timetableEntry.slot'])
            ->where('student_id', $student->id)
            ->whereHas('timetableEntry', fn($q) => $q->where('subject_id', $subject->id));

        if ($semesterId) {
            $query->whereHas('timetableEntry', fn($q) => $q->where('semester_id', $semesterId));
        }

        $sessions = $query->orderByDesc('date')->paginate(30);

        $total   = $sessions->total();
        $present = Attendance::where('student_id', $student->id)
            ->whereHas('timetableEntry', fn($q) => $q->where('subject_id', $subject->id))
            ->whereIn('status', ['present', 'late'])->count();
        $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        return view('student.attendance-sessions', compact('subject', 'sessions', 'total', 'present', 'pct'));
    }
}
