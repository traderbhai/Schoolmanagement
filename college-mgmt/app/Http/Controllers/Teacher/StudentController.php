<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcCourseGroupMember, AcademicPmcTimetableGenerationItem, Attendance, Student, TimetableEntry, Semester, Term};
use App\Services\CanonicalTimetableBridgeService;
use Illuminate\Database\Eloquent\Builder;
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

        app(CanonicalTimetableBridgeService::class)
            ->ensureTeacherSemesterBridges((int) $teacher->id, $currentSemester, $request->user());

        $canonicalItems = $this->officialPmcTeacherItems((int) $teacher->id, $currentSemester);

        if ($canonicalItems->isNotEmpty()) {
            $courseGroupIds = $canonicalItems->pluck('course_group_id')->filter()->unique()->values();
            $studentIds = AcademicPmcCourseGroupMember::whereIn('course_group_id', $courseGroupIds)
                ->where('status', 'active')
                ->pluck('student_id')
                ->filter()
                ->unique()
                ->values();

            $students = $studentIds->isEmpty()
                ? collect()
                : Student::whereIn('id', $studentIds)
                    ->with(['user', 'course', 'department'])
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

            $entryIds = $canonicalItems->pluck('operational_timetable_entry_id')
                ->merge(TimetableEntry::whereIn('pmc_generation_item_id', $canonicalItems->pluck('id'))->pluck('id'))
                ->filter()
                ->unique()
                ->values();

            $this->attachAttendanceSummary($students, $entryIds);

            return view('teacher.students', compact('students', 'currentSemester', 'search'));
        }

        $teacherEntries = TimetableEntry::where('teacher_id', $teacher->id)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->where(fn ($query) => $this->publishedTimetableScope($query))
            ->get(['id', 'subject_id', 'semester_id', 'term_id', 'program_id', 'batch_id']);

        $entryIds = $teacherEntries->pluck('id')->filter()->unique();

        if ($entryIds->isEmpty()) {
            $students = collect();

            return view('teacher.students', compact('students', 'currentSemester', 'search'));
        }

        $students = Student::where(function ($query) use ($teacherEntries) {
            $teacherEntries->each(function (TimetableEntry $entry) use ($query) {
                $termIds = $this->termIdsForEntry($entry);

                $query->orWhereHas('enrollments', function ($enrollment) use ($entry) {
                    $enrollment->where('subject_id', $entry->subject_id)
                        ->where('semester_id', $entry->semester_id)
                        ->where('status', 'active');
                })->orWhereHas('subjectEnrollments', function ($enrollment) use ($entry, $termIds) {
                    $enrollment->where('subject_id', $entry->subject_id)
                        ->where('status', 'active');

                    $termIds === []
                        ? $enrollment->whereRaw('1 = 0')
                        : $enrollment->whereIn('term_id', $termIds);
                });
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

        $this->attachAttendanceSummary($students, $entryIds);

        return view('teacher.students', compact('students', 'currentSemester', 'search'));
    }

    private function officialPmcTeacherItems(int $teacherId, ?Semester $currentSemester)
    {
        return AcademicPmcTimetableGenerationItem::with(['courseGroup.term', 'timetableVersion'])
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('course_group_id')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->when($currentSemester, function (Builder $query) use ($currentSemester) {
                $query->where(function (Builder $scope) use ($currentSemester) {
                    $scope->whereHas('term', fn (Builder $term) => $this->semesterTermScope($term, $currentSemester))
                        ->orWhereHas('courseGroup.term', fn (Builder $term) => $this->semesterTermScope($term, $currentSemester));
                });
            })
            ->get();
    }

    private function attachAttendanceSummary($students, $entryIds): void
    {
        $attendanceSummary = $entryIds->isEmpty() || $students->isEmpty()
            ? collect()
            : Attendance::selectRaw(
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
    }

    private function semesterTermScope(Builder $term, Semester $semester): void
    {
        $term->where(function (Builder $scope) use ($semester) {
            $scope->where('term_number', $semester->number)
                ->orWhere('name', $semester->name);
        });
    }

    private function publishedTimetableScope($query)
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
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
            ->when($entry->program_id, fn ($query) => $query->where('program_id', $entry->program_id))
            ->when($entry->batch_id, fn ($query) => $query->where('batch_id', $entry->batch_id))
            ->where(fn ($query) => $query->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
