<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{TimetableEntry, Student, Attendance, Semester, Term};
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private function enrolledStudentIdsForEntry(TimetableEntry $entry)
    {
        return $this->enrolledStudentsForEntry($entry)->pluck('id');
    }

    private function enrolledStudentsForEntry(TimetableEntry $entry)
    {
        $termIds = $this->termIdsForEntry($entry);

        return Student::where(function ($query) use ($entry, $termIds) {
            $query->whereHas('enrollments', fn($q) =>
                $q->where('semester_id', $entry->semester_id)
                  ->where('subject_id', $entry->subject_id)
                  ->whereIn('status', ['active', 'enrolled'])
            )->orWhereHas('subjectEnrollments', function ($q) use ($entry, $termIds) {
                $q->where('subject_id', $entry->subject_id)
                  ->where('status', 'active');

                $termIds === []
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('term_id', $termIds);
            });
        });
    }

    private function termIdsForEntry(TimetableEntry $entry): array
    {
        if ($entry->term_id) {
            return [(int) $entry->term_id];
        }

        if (! $entry->semester_id) {
            return [];
        }

        $semester = $entry->semester ?: Semester::find($entry->semester_id);
        if (! $semester) {
            return [];
        }

        return Term::query()
            ->when($entry->program_id, fn($q) => $q->where('program_id', $entry->program_id))
            ->when($entry->batch_id, fn($q) => $q->where('batch_id', $entry->batch_id))
            ->where(fn($q) => $q->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    public function mark(Request $request)
    {
        $teacher = auth()->user()->teacher;
        $date = $request->date ?? today()->toDateString();
        if (!$teacher) {
            $entries = collect();
            $entry = null;
            $students = collect();
            $currentSemester = Semester::current();
            $canMarkAttendance = false;
            $actionBlockedReason = 'Your login has the Teacher role, but no teacher profile is attached yet. Ask Admin/Academics to link your teacher profile before marking attendance.';

            return view('teacher.attendance.mark', compact('entries', 'entry', 'students', 'date', 'currentSemester', 'canMarkAttendance', 'actionBlockedReason'));
        }

        $canMarkAttendance = $teacher->status === 'active';
        $actionBlockedReason = $canMarkAttendance ? null : 'Attendance marking is locked because this teacher profile is not active.';
        $dayOfWeek = (int) date('N', strtotime($date));

        // Teacher's timetable entries for the selected day
        $currentSemester = Semester::current();
        $entries = TimetableEntry::with(['subject', 'course', 'classroom', 'slot'])
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayOfWeek)
            ->where(fn($query) => $this->publishedTimetableScope($query))
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->get();

        $entry = null;
        $students = collect();

        if ($request->entry_id) {
            $entry = TimetableEntry::with(['subject','course','slot','classroom'])
                ->where('teacher_id', $teacher->id)
                ->where('day_of_week', $dayOfWeek)
                ->where(fn($query) => $this->publishedTimetableScope($query))
                ->findOrFail($request->entry_id);

            $students = $this->enrolledStudentsForEntry($entry)->with(['user',
                'attendances' => fn($q) => $q->where('timetable_entry_id', $entry->id)
                                             ->where('date', $date)
            ])->orderBy('roll_number')->get();
        }

        return view('teacher.attendance.mark', compact('entries', 'entry', 'students', 'date', 'currentSemester', 'canMarkAttendance', 'actionBlockedReason'));
    }

    public function store(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_unless($teacher?->status === 'active', 403, 'Only active teachers can mark attendance.');

        $request->validate([
            'timetable_entry_id' => 'required|exists:timetable_entries,id',
            'date'               => 'required|date|before_or_equal:today',
            'attendance'         => 'required|array',
            'attendance.*'       => 'required|in:present,absent,late,excused',
        ]);

        $entry = TimetableEntry::where('teacher_id', $teacher->id)
            ->where(fn($query) => $this->publishedTimetableScope($query))
            ->findOrFail($request->timetable_entry_id);

        $attendanceDay = (int) date('N', strtotime($request->date));
        if ((int) $entry->day_of_week !== $attendanceDay) {
            return back()
                ->withErrors(['date' => 'Attendance date must match the selected class schedule.'])
                ->withInput();
        }

        $allowedStudentIds = $this->enrolledStudentIdsForEntry($entry)->map(fn ($id) => (string) $id)->all();
        $submittedStudentIds = array_map('strval', array_keys($request->attendance));
        abort_unless(empty(array_diff($submittedStudentIds, $allowedStudentIds)), 403, 'Attendance includes students outside this class.');

        foreach ($request->attendance as $studentId => $status) {
            Attendance::updateOrCreate(
                ['student_id' => $studentId, 'timetable_entry_id' => $request->timetable_entry_id, 'date' => $request->date],
                ['status' => $status, 'marked_by' => auth()->id()]
            );
        }

        return redirect()->route('teacher.attendance.mark')->with('success', 'Attendance saved successfully.');
    }

    private function publishedTimetableScope($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($scope) {
                $scope->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
            });
    }
}
