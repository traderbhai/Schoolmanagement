<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Attendance, TimetableEntry, Student, Semester, Course};
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request) {
        $semesters = Semester::with('academicYear')->get();
        $courses = Course::where('is_active',true)->get();
        return view('admin.attendance.index', compact('semesters','courses'));
    }

    public function mark(Request $request) {
        $request->validate([
            'timetable_entry_id' => 'required|exists:timetable_entries,id',
            'date'               => 'required|date',
        ]);

        $entry = TimetableEntry::with(['course','subject'])->findOrFail($request->timetable_entry_id);
        $students = Student::whereHas('enrollments', fn($q) =>
            $q->where('semester_id', $entry->semester_id)->where('subject_id', $entry->subject_id)
        )->with(['user',
            'attendances' => fn($q) => $q->where('timetable_entry_id', $entry->id)->where('date', $request->date)
        ])->get();

        return view('admin.attendance.mark', compact('entry','students','request'));
    }

    public function store(Request $request) {
        $request->validate([
            'timetable_entry_id' => 'required|exists:timetable_entries,id',
            'date'               => 'required|date',
            'attendance'         => 'required|array',
        ]);

        foreach ($request->attendance as $studentId => $status) {
            Attendance::updateOrCreate(
                ['student_id' => $studentId, 'timetable_entry_id' => $request->timetable_entry_id, 'date' => $request->date],
                ['status' => $status]
            );
        }

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance marked.');
    }

    public function report(Request $request) {
        $report = null;
        if ($request->student_id && $request->semester_id) {
            $report = Attendance::with(['timetableEntry.subject'])
                ->where('student_id', $request->student_id)
                ->whereHas('timetableEntry', fn($q) => $q->where('semester_id', $request->semester_id))
                ->get()
                ->groupBy('timetableEntry.subject.name');
        }
        $students = Student::with('user')->where('status','active')->get();
        $semesters = Semester::with('academicYear')->get();
        return view('admin.attendance.report', compact('report','students','semesters','request'));
    }
}
