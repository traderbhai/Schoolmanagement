<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{TimetableEntry, Student, Attendance, Semester};
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function mark(Request $request)
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) return redirect()->route('teacher.dashboard');

        $date = $request->date ?? today()->toDateString();
        $dayOfWeek = (int) date('N', strtotime($date));

        // Teacher's timetable entries for the selected day
        $currentSemester = Semester::current();
        $entries = TimetableEntry::with(['subject', 'course', 'classroom', 'slot'])
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->get();

        $entry = null;
        $students = collect();

        if ($request->entry_id) {
            $entry = TimetableEntry::with(['subject','course','slot','classroom'])
                ->where('teacher_id', $teacher->id)
                ->findOrFail($request->entry_id);

            $students = Student::whereHas('enrollments', fn($q) =>
                $q->where('semester_id', $entry->semester_id)
                  ->where('subject_id', $entry->subject_id)
            )->with(['user',
                'attendances' => fn($q) => $q->where('timetable_entry_id', $entry->id)
                                             ->where('date', $date)
            ])->orderBy('roll_number')->get();
        }

        return view('teacher.attendance.mark', compact('entries', 'entry', 'students', 'date', 'currentSemester'));
    }

    public function store(Request $request)
    {
        $teacher = auth()->user()->teacher;
        $request->validate([
            'timetable_entry_id' => 'required|exists:timetable_entries,id',
            'date'               => 'required|date',
            'attendance'         => 'required|array',
        ]);

        // Verify this entry belongs to the teacher
        TimetableEntry::where('teacher_id', $teacher->id)->findOrFail($request->timetable_entry_id);

        foreach ($request->attendance as $studentId => $status) {
            Attendance::updateOrCreate(
                ['student_id' => $studentId, 'timetable_entry_id' => $request->timetable_entry_id, 'date' => $request->date],
                ['status' => $status, 'marked_by' => auth()->id()]
            );
        }

        return redirect()->route('teacher.attendance.mark')->with('success', 'Attendance saved successfully.');
    }
}
