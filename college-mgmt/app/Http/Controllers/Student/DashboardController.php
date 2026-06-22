<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcCourseGroupMember, AcademicPmcTimetableGenerationItem, Semester, Notice, TimetableSlot, Attendance, FeeStructure, FeePayment, FeeDemand, ExamResult, Exam, Enrollment, Student, StudentSubjectEnrollment, Term, TimetableEntry, HostelFeeDemand};
use App\Models\{Assignment, Quiz, LeaveApplication, AcademicEvent, SubjectAnnouncement};
use App\Services\TimetableService;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('login');

        $currentSemester = Semester::current();
        $notices = Notice::active()->where(fn($q) => $q->where('audience','all')->orWhere('audience','students'))->latest()->take(3)->get();
        $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
        $todayDay = now()->dayOfWeekIso; // 1=Mon...6=Sat
        $todayClasses = $currentSemester
            ? $this->studentTimetableEntriesForDay($student, $currentSemester->id, $todayDay)
            : collect();

        // Attendance overall - only published timetable history is student-visible.
        $attendanceQuery = $this->studentPublishedAttendanceQuery($student);
        $totalAttendance   = (clone $attendanceQuery)->count();
        $presentAttendance = (clone $attendanceQuery)->whereIn('status', ['present','late'])->count();
        $attendanceOverall = $totalAttendance > 0 ? round(($presentAttendance / $totalAttendance) * 100) : null;

        // Per-subject attendance - flag low ones
        $lowAttendanceSubjects = [];
        $attendanceBySubject = $this->studentPublishedAttendanceQuery($student)
            ->with('timetableEntry.subject')
            ->get()
            ->groupBy(fn($a) => $a->timetableEntry?->subject?->name ?? 'Unknown');
        foreach ($attendanceBySubject as $subjName => $records) {
            $total   = $records->count();
            $present = $records->whereIn('status', ['present','late'])->count();
            $pct     = $total > 0 ? round(($present / $total) * 100) : 0;
            if ($pct < 75 && $total > 0) {
                $lowAttendanceSubjects[] = ['subject' => $subjName, 'pct' => $pct, 'total' => $total, 'present' => $present];
            }
        }

        // SGPA - from current semester
        $sgpa = null;
        if ($currentSemester) {
            $results = ExamResult::with('exam')
                ->whereHas('exam', fn($q) => $q->where('semester_id', $currentSemester->id)->whereNotNull('published_at'))
                ->where('student_id', $student->id)
                ->where('is_absent', false)
                ->get();
            if ($results->count() > 0) {
                $totalGp = $results->sum(fn($r) => $r->exam->total_marks > 0
                    ? ($r->marks_obtained / $r->exam->total_marks) * 10 : 0);
                $sgpa = round($totalGp / $results->count(), 2);
            }
        }

        // CGPA - avg across all semesters
        $cgpa = null;
        try {
            $allResults = ExamResult::with('exam')
                ->where('student_id', $student->id)
                ->where('is_absent', false)
                ->whereHas('exam', fn($q) => $q->whereNotNull('published_at'))
                ->get()
                ->filter(fn($r) => $r->exam && $r->exam->total_marks > 0);
            if ($allResults->count() > 0) {
                $totalGp = $allResults->sum(fn($r) => ($r->marks_obtained / $r->exam->total_marks) * 10);
                $cgpa = round($totalGp / $allResults->count(), 2);
            }
        } catch (\Exception $e) { $cgpa = null; }

        // Fee balance - use FeeDemand as source of truth
        $feeOutstanding = 0;
        try {
            $feeOutstanding = FeeDemand::where('student_id', $student->id)
                ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                ->get(['final_amount', 'penalty_amount'])
                ->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));
        } catch (\Exception $e) {
            $feeDue  = FeeStructure::where('course_id', $student->course_id)->sum('amount');
            $feePaid = FeePayment::where('student_id', $student->id)->where('status', 'paid')->sum('amount_paid');
            $feeOutstanding = max(0, $feeDue - $feePaid);
        }
        $feeOutstanding += (float) HostelFeeDemand::where('student_id', $student->id)
            ->where('status', 'pending')
            ->sum('amount');

        // Upcoming assignments (due within 7 days)
        $upcomingAssignments = collect();
        try {
            $subjectIds = $student->subjects()->pluck('subjects.id');
            $upcomingAssignments = Assignment::whereIn('subject_id', $subjectIds)
                ->where('is_published', true)
                ->where('due_at', '>', now())
                ->where('due_at', '<=', now()->addDays(7))
                ->with(['subject', 'submissions' => fn($q) => $q->where('student_id', $student->id)])
                ->orderBy('due_at')
                ->get();
        } catch (\Exception $e) {}

        $pendingAssignmentCount = $upcomingAssignments
            ->filter(fn($assignment) => ! $assignment->submissions->first()
                || ! in_array($assignment->submissions->first()->status, ['submitted', 'graded']))
            ->count();

        // Upcoming exams
        $upcomingExams = collect();
        try {
            if ($student->program_id) {
                $examEligibility = $this->examEligibilityScope($student);
                $upcomingExams = Exam::where('program_id', $student->program_id)
                    ->where(function ($query) use ($examEligibility) {
                        $query->whereNull('subject_id');

                        if ($examEligibility['subject_ids'] !== []) {
                            $query->orWhereIn('subject_id', $examEligibility['subject_ids']);
                        }
                    })
                    ->where(function ($query) use ($examEligibility) {
                        $query->where(fn($scope) => $scope->whereNull('term_id')->whereNull('semester_id'));

                        if ($examEligibility['term_ids'] !== []) {
                            $query->orWhereIn('term_id', $examEligibility['term_ids']);
                        }

                        if ($examEligibility['semester_ids'] !== []) {
                            $query->orWhereIn('semester_id', $examEligibility['semester_ids']);
                        }
                    })
                    ->where('exam_date', '>', now())
                    ->whereNull('published_at')
                    ->with('subject')
                    ->orderBy('exam_date')
                    ->take(5)
                    ->get();
            }
        } catch (\Exception $e) {}

        // Upcoming academic events (next 30 days)
        $upcomingEvents = collect();
        try {
            $upcomingEvents = AcademicEvent::where('is_public', true)
                ->where('start_date', '>=', today())
                ->where('start_date', '<=', today()->addDays(30))
                ->where(fn($q) => $q->whereNull('program_id')->orWhere('program_id', $student->program_id))
                ->orderBy('start_date')
                ->take(5)
                ->get();
        } catch (\Exception $e) {}

        // Pending leave applications
        $pendingLeaves = 0;
        try {
            $pendingLeaves = LeaveApplication::where('student_id', $student->id)
                ->where('status', 'pending')->count();
        } catch (\Exception $e) {}

        return view('student.dashboard', compact(
            'student', 'currentSemester', 'notices', 'slots', 'todayClasses',
            'attendanceOverall', 'lowAttendanceSubjects', 'sgpa', 'cgpa', 'feeOutstanding',
            'upcomingAssignments', 'pendingAssignmentCount', 'upcomingExams', 'upcomingEvents', 'pendingLeaves'
        ));
    }

    private function studentTimetableEntriesForDay(Student $student, int $semesterId, int $dayOfWeek)
    {
        $subjectIds = $this->enrolledSubjectIds($student);

        if ($subjectIds === []) {
            return collect();
        }

        $courseGroupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('courseGroup', fn (Builder $group) => $group->whereIn('subject_id', $subjectIds))
            ->pluck('course_group_id')
            ->unique()
            ->values();

        if ($courseGroupIds->isNotEmpty()) {
            $canonicalEntries = AcademicPmcTimetableGenerationItem::whereIn('course_group_id', $courseGroupIds)
                ->where('day_of_week', $dayOfWeek)
                ->whereIn('status', ['scheduled', 'published', 'locked'])
                ->where('official_status', 'published')
                ->whereNotNull('timetable_version_id')
                ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
                ->when($student->program_id, fn($q) => $q->where(function ($query) use ($student) {
                    $query->whereNull('program_id')->orWhere('program_id', $student->program_id);
                }))
                ->when($student->batch_id, fn($q) => $q->where(function ($query) use ($student) {
                    $query->whereNull('batch_id')->orWhere('batch_id', $student->batch_id);
                }))
                ->when($student->current_term_id, fn($q) => $q->where(function ($query) use ($student) {
                    $query->whereNull('term_id')->orWhere('term_id', $student->current_term_id);
                }))
                ->with(['subject', 'courseGroup.subject', 'classroom', 'teacher.user', 'slot', 'timetableVersion'])
                ->get()
                ->sortBy(fn($entry) => optional($entry->slot)->sort_order ?? 0)
                ->groupBy('timetable_slot_id');

            if ($canonicalEntries->isNotEmpty()) {
                return $canonicalEntries;
            }
        }

        return TimetableEntry::whereIn('subject_id', $subjectIds)
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
            })
            ->when($student->program_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('program_id')->orWhere('program_id', $student->program_id);
            }))
            ->when($student->batch_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('batch_id')->orWhere('batch_id', $student->batch_id);
            }))
            ->when($student->current_term_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('term_id')->orWhere('term_id', $student->current_term_id);
            }))
            ->with(['subject', 'classroom', 'teacher.user', 'slot'])
            ->get()
            ->sortBy(fn($entry) => optional($entry->slot)->sort_order ?? 0)
            ->groupBy('timetable_slot_id');
    }

    private function studentPublishedAttendanceQuery(Student $student)
    {
        $subjectIds = $this->enrolledSubjectIds($student);

        return Attendance::where('student_id', $student->id)
            ->when($subjectIds === [], fn($query) => $query->whereRaw('1 = 0'))
            ->whereHas('timetableEntry', function ($query) use ($student, $subjectIds) {
                $query->whereIn('subject_id', $subjectIds)
                    ->where('is_active', true)
                    ->where('status', 'published')
                    ->where(function ($versionQuery) {
                        $versionQuery->whereNull('timetable_version_id')
                            ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
                    })
                    ->when($student->program_id, fn($scope) => $scope->where(function ($programScope) use ($student) {
                        $programScope->whereNull('program_id')->orWhere('program_id', $student->program_id);
                    }))
                    ->when($student->batch_id, fn($scope) => $scope->where(function ($batchScope) use ($student) {
                        $batchScope->whereNull('batch_id')->orWhere('batch_id', $student->batch_id);
                    }))
                    ->when($student->current_term_id, fn($scope) => $scope->where(function ($termScope) use ($student) {
                        $termScope->whereNull('term_id')->orWhere('term_id', $student->current_term_id);
                    }));
            });
    }

    private function examEligibilityScope(Student $student): array
    {
        $canonical = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->get(['subject_id', 'term_id']);
        $canonicalTermIds = $canonical->pluck('term_id')->filter()->map(fn($id) => (int) $id)->unique()->values();
        $canonicalTerms = $canonicalTermIds->isEmpty()
            ? collect()
            : Term::whereIn('id', $canonicalTermIds)->get(['id', 'term_number', 'name']);
        $mappedSemesterIds = $canonicalTerms->flatMap(function (Term $term) {
            return Semester::where('number', $term->term_number)
                ->orWhere('name', $term->name)
                ->pluck('id');
        });

        $legacy = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->get(['subject_id', 'semester_id']);

        return [
            'subject_ids' => $canonical->pluck('subject_id')
                ->merge($legacy->pluck('subject_id'))
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'term_ids' => $canonicalTermIds->all(),
            'semester_ids' => $legacy->pluck('semester_id')
                ->merge($mappedSemesterIds)
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function enrolledSubjectIds(Student $student): array
    {
        return StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->when($student->current_term_id, fn($query) => $query->where('term_id', $student->current_term_id))
            ->pluck('subject_id')
            ->merge(Enrollment::where('student_id', $student->id)
                ->whereIn('status', ['active', 'enrolled'])
                ->when($student->current_term_id, fn($query) => $query->where('term_id', $student->current_term_id))
                ->pluck('subject_id'))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
