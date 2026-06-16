<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{Attendance, Student, TimetableEntry, Semester};
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $teacher = auth()->user()->teacher;
        $currentSemester = Semester::current();
        $search = trim((string) $request->query('search', ''));

        if (! $teacher) {
            $students = collect();

            return view('teacher.students', compact('students', 'currentSemester', 'search'));
        }

        $teacherEntries = TimetableEntry::where('teacher_id', $teacher->id)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->where('is_active', true)
            ->get(['id', 'subject_id', 'semester_id', 'term_id']);

        $entryIds = $teacherEntries->pluck('id')->filter()->unique();
        $subjectIds = $teacherEntries->pluck('subject_id')->filter()->unique();
        $termIds = $teacherEntries->pluck('term_id')->filter()->unique();

        $students = Student::where(function ($query) use ($subjectIds, $termIds, $currentSemester) {
            $query->whereHas('enrollments', function ($enrollment) use ($subjectIds, $currentSemester) {
                $enrollment->whereIn('subject_id', $subjectIds)
                    ->where('status', 'active')
                    ->when($currentSemester, fn ($semesterQuery) => $semesterQuery->where('semester_id', $currentSemester->id));
            })->orWhereHas('subjectEnrollments', function ($enrollment) use ($subjectIds, $termIds) {
                $enrollment->whereIn('subject_id', $subjectIds)
                    ->where('status', 'active')
                    ->when($termIds->isNotEmpty(), fn ($termQuery) => $termQuery->whereIn('term_id', $termIds));
            });
        })->with(['user', 'course', 'department'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($scope) use ($search) {
                    $scope->whereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('enrollment_number', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('roll_number')
            ->get();

        $attendanceSummary = Attendance::selectRaw(
                "student_id, COUNT(*) as total_sessions, SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_sessions"
            )
            ->whereIn('timetable_entry_id', $entryIds)
            ->whereIn('student_id', $students->pluck('id'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $students->each(function (Student $student) use ($attendanceSummary) {
            $summary = $attendanceSummary->get($student->id);
            $total = (int) ($summary->total_sessions ?? 0);
            $attended = (int) ($summary->attended_sessions ?? 0);

            $student->teacher_attendance_total = $total;
            $student->teacher_attendance_attended = $attended;
            $student->teacher_attendance_percentage = $total > 0
                ? round(($attended / $total) * 100, 1)
                : null;
        });

        return view('teacher.students', compact('students', 'currentSemester', 'search'));
    }
}
