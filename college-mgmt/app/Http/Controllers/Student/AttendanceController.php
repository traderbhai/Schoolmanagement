<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Attendance, Semester, TimetableSlot};
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
}
