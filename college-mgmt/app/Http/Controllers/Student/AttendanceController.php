<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Attendance, Enrollment, Semester, Student, StudentSubjectEnrollment, Subject, Term};
use App\Services\GradeService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private GradeService $gradeService) {}

    public function index(Request $request)
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');

        $semesters = $this->gradeService->semestersForStudent($student->id)
            ->load('academicYear')
            ->sortByDesc('number')
            ->values();
        $currentSemester = Semester::current() ?? $semesters->first();
        $semesterId = $request->semester_id ?? optional($currentSemester)->id;

        $report = [];
        if ($semesterId) {
            $termIds = $this->termIdsForSemester($student, (int) $semesterId);
            $attendances = Attendance::with(['timetableEntry.subject', 'timetableEntry.slot'])
                ->where('student_id', $student->id)
                ->whereHas('timetableEntry', function ($q) use ($semesterId, $termIds) {
                    $q->where('semester_id', $semesterId);

                    if ($termIds !== []) {
                        $q->orWhereIn('term_id', $termIds);
                    }
                })
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
        abort_unless($this->studentIsEnrolledInSubject($student, $subject), 403);

        $semesterId = $request->semester_id;
        $termIds = $semesterId ? $this->termIdsForSemester($student, (int) $semesterId) : [];

        $query = Attendance::with(['timetableEntry.slot'])
            ->where('student_id', $student->id)
            ->whereHas('timetableEntry', fn($q) => $q->where('subject_id', $subject->id));

        if ($semesterId) {
            $query->whereHas('timetableEntry', function ($q) use ($semesterId, $termIds) {
                $q->where('semester_id', $semesterId);

                if ($termIds !== []) {
                    $q->orWhereIn('term_id', $termIds);
                }
            });
        }

        $sessions = $query->orderByDesc('date')->paginate(30);

        $total   = $sessions->total();
        $present = Attendance::where('student_id', $student->id)
            ->whereHas('timetableEntry', fn($q) => $q->where('subject_id', $subject->id))
            ->whereIn('status', ['present', 'late'])->count();
        $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        return view('student.attendance-sessions', compact('subject', 'sessions', 'total', 'present', 'pct'));
    }

    private function studentIsEnrolledInSubject(Student $student, Subject $subject): bool
    {
        return StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'active')
            ->exists()
            || Enrollment::where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->whereIn('status', ['active', 'enrolled'])
                ->exists();
    }

    private function termIdsForSemester(Student $student, int $semesterId): array
    {
        $semester = Semester::find($semesterId);
        if (! $semester) {
            return [];
        }

        return Term::query()
            ->when($student->program_id, fn($q) => $q->where('program_id', $student->program_id))
            ->when($student->batch_id, fn($q) => $q->where('batch_id', $student->batch_id))
            ->where(fn($q) => $q->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }
}
