<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\CourseFeedback;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\LeaveApplication;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicProgramLeadershipService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function dashboard(User $user): array
    {
        $portfolio = $this->programPortfolio($user);
        $delivery = $this->courseDelivery($user);
        $students = $this->studentSuccess($user);
        $quality = $this->qualitySignals($user);

        return [
            'scopeSummary' => $this->scopeSummary($user),
            'kpis' => [
                'programs' => $portfolio['metrics']['active_programs'],
                'active_students' => $portfolio['metrics']['active_students'],
                'delivery_gaps' => $delivery['metrics']['faculty_gaps'] + $delivery['metrics']['draft_timetable'],
                'student_risk' => $students['metrics']['attendance_risk'] + $students['metrics']['weak_performance'],
            ],
            'portfolio' => $portfolio,
            'delivery' => $delivery,
            'students' => $students,
            'quality' => $quality,
            'reports' => $this->reports($user),
        ];
    }

    public function programPortfolio(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $programs = $this->applyProgramScope(Program::withCount(['students', 'subjects'])->where('is_active', true), $programIds, 'id')
            ->orderBy('name')
            ->limit(25)
            ->get();

        $pendingChanges = $this->applyProgramScope(
            CurriculumChange::with(['program', 'subject'])->whereIn('status', ['submitted', 'under_review']),
            $programIds
        )->latest('submitted_at')->limit(25)->get();

        return [
            'title' => 'Program Portfolio',
            'description' => 'Assigned programs, active student strength, curriculum change pressure, and program readiness.',
            'metrics' => [
                'active_programs' => $programs->count(),
                'active_students' => $this->applyProgramScope(Student::where('status', 'active'), $programIds)->count(),
                'active_subjects' => $this->applyProgramScope(Subject::where('is_active', true), $programIds)->count(),
                'pending_curriculum_changes' => $pendingChanges->count(),
            ],
            'items' => collect($programs->map(fn (Program $program) => [
                'title' => $program->name,
                'subtitle' => $program->code . ' - ' . $program->students_count . ' students, ' . $program->subjects_count . ' subjects',
                'status' => 'Active',
                'metric_keys' => ['active_programs', 'programs'],
                'action' => route('academics.program-leadership.portfolio'),
            ])->values())->concat($pendingChanges->map(fn (CurriculumChange $change) => [
                'title' => $change->title,
                'subtitle' => ($change->program?->code ?? 'Program') . ' - ' . ($change->subject?->code ?? 'Program level'),
                'status' => ucfirst(str_replace('_', ' ', $change->status)),
                'metric_keys' => ['pending_curriculum_changes'],
                'action' => route('academic.curriculum-changes.index'),
            ])->values())->values(),
        ];
    }

    public function courseDelivery(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $assignedSubjectIds = SubjectFacultyAssignment::query()->pluck('subject_id');
        $facultyGaps = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', $assignedSubjectIds),
            $programIds
        )->orderBy('name')->limit(25)->get();

        $draftTimetable = $this->applyProgramScope(
            TimetableEntry::with(['program', 'subject', 'teacher.user'])
                ->where('is_active', true)
                ->where(fn (Builder $query) => $this->unpublishedTimetableScope($query)),
            $programIds
        )->orderBy('day_of_week')->limit(25)->get();

        return [
            'title' => 'Course Delivery',
            'description' => 'Faculty assignment, timetable readiness, session load, and course-delivery exceptions.',
            'metrics' => [
                'faculty_gaps' => $facultyGaps->count(),
                'draft_timetable' => $draftTimetable->count(),
                'published_slots' => $this->applyProgramScope(TimetableEntry::where(fn (Builder $query) => $this->publishedTimetableScope($query)), $programIds)->count(),
                'faculty_assignments' => $this->applyProgramScope(SubjectFacultyAssignment::query(), $programIds)->count(),
            ],
            'items' => collect($facultyGaps->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - faculty assignment pending',
                'status' => 'Faculty gap',
                'metric_keys' => ['faculty_gaps', 'delivery_gaps'],
                'action' => route('academics.program-leadership.course-delivery'),
            ])->values())->concat($draftTimetable->map(fn (TimetableEntry $entry) => [
                'title' => $entry->subject?->name ?? 'Timetable entry',
                'subtitle' => ($entry->program?->code ?? 'Program') . ' - ' . $entry->day_name,
                'status' => ucfirst($entry->status ?? 'draft'),
                'metric_keys' => ['draft_timetable', 'delivery_gaps'],
                'action' => route('academics.program-leadership.course-delivery'),
            ])->values())->values(),
        ];
    }

    public function studentSuccess(User $user, bool $includeActiveStudentRows = false): array
    {
        $programIds = $this->visibleProgramIds($user);
        $activeStudents = $this->applyProgramScope(
            Student::with(['user', 'program', 'batch'])->where('status', 'active'),
            $programIds
        )->orderBy('enrollment_number')->get();
        $studentIds = $activeStudents->pluck('id');

        $attendanceRisk = Attendance::with('student.user')
            ->selectRaw('student_id, count(*) as exception_count')
            ->whereHas('timetableEntry', fn (Builder $query) => $this->publishedTimetableScope($query))
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['absent', 'late'])
            ->groupBy('student_id')
            ->having('exception_count', '>=', 2)
            ->limit(25)
            ->get();

        $weakPerformance = ExamResult::with(['student.user', 'exam.subject'])
            ->whereIn('student_id', $studentIds)
            ->whereHas('exam', fn (Builder $query) => $query
                ->whereNotNull('published_at')
                ->whereColumn('exam_results.marks_obtained', '<', 'exams.passing_marks'))
            ->limit(25)
            ->get();

        $pendingLeaves = LeaveApplication::with('student.user')
            ->whereIn('student_id', $studentIds)
            ->where('status', 'pending')
            ->limit(25)
            ->get();

        return [
            'title' => 'Student Success',
            'description' => 'At-risk students, weak performance, leave reviews, mentor needs, and intervention queues.',
            'metrics' => [
                'active_students' => $activeStudents->count(),
                'attendance_risk' => $attendanceRisk->count(),
                'weak_performance' => $weakPerformance->count(),
                'pending_leaves' => $pendingLeaves->count(),
                'mentor_gaps' => $this->applyProgramScope(Student::whereNull('mentor_id'), $programIds)->count(),
            ],
            'items' => collect($includeActiveStudentRows ? $activeStudents->map(fn (Student $student) => [
                'title' => $this->studentLabel($student, $student->id),
                'subtitle' => trim(($student->program?->code ?? 'Program') . ' - ' . ($student->batch?->name ?? 'No batch') . ' - ' . ($student->enrollment_number ?? 'No enrollment')),
                'status' => 'Active student',
                'metric_keys' => ['active_students'],
                'action' => route('academics.program-leadership.student-success', ['metric' => 'active_students', 'student_id' => $student->id]),
            ])->values() : [])->concat($attendanceRisk->map(fn ($row) => [
                'title' => $this->studentLabel($row->student, $row->student_id),
                'subtitle' => $row->exception_count . ' attendance exceptions',
                'status' => 'Intervention due',
                'metric_keys' => ['attendance_risk', 'student_risk'],
                'action' => route('academics.program-leadership.student-success'),
            ])->values())->concat($weakPerformance->map(fn (ExamResult $result) => [
                'title' => $this->studentLabel($result->student, $result->student_id),
                'subtitle' => ($result->exam?->subject?->code ?? 'Exam') . ' - ' . $result->marks_obtained . '/' . $result->exam?->passing_marks,
                'status' => 'Weak performance',
                'metric_keys' => ['weak_performance', 'student_risk'],
                'action' => route('academics.program-leadership.student-success'),
            ])->values())->concat($pendingLeaves->map(fn (LeaveApplication $leave) => [
                'title' => $this->studentLabel($leave->student, $leave->student_id),
                'subtitle' => $leave->leave_type . ' from ' . $leave->from_date?->toDateString(),
                'status' => 'Leave pending',
                'metric_keys' => ['pending_leaves'],
                'action' => route('academics.program-leadership.student-success'),
            ])->values())->values(),
        ];
    }

    public function qualitySignals(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $programsWithoutPo = $this->applyProgramScope(
            Program::where('is_active', true)->whereNotIn('id', ProgramOutcome::query()->pluck('program_id')),
            $programIds,
            'id'
        )->limit(25)->get();

        $subjectsWithoutCo = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', CourseOutcome::query()->pluck('subject_id')),
            $programIds
        )->limit(25)->get();

        $lowFeedback = CourseFeedback::with('subject.program')
            ->selectRaw('subject_id, avg(overall_rating) as avg_rating, count(*) as response_count')
            ->whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->groupBy('subject_id')
            ->having('avg_rating', '<', 3.5)
            ->limit(25)
            ->get();

        return [
            'title' => 'Quality Signals',
            'description' => 'OBE gaps, feedback action plans, exam outcomes, and quality follow-up signals for assigned programs.',
            'metrics' => [
                'program_outcome_gaps' => $programsWithoutPo->count(),
                'course_outcome_gaps' => $subjectsWithoutCo->count(),
                'low_feedback_subjects' => $lowFeedback->count(),
                'exams_this_year' => $this->applyProgramScope(Exam::whereYear('exam_date', now()->year), $programIds)->count(),
            ],
            'items' => collect($programsWithoutPo->map(fn (Program $program) => [
                'title' => $program->name,
                'subtitle' => $program->code . ' - PO setup missing',
                'status' => 'Quality gap',
                'metric_keys' => ['program_outcome_gaps'],
                'action' => route('academic.obe.po.index', ['program_id' => $program->id]),
            ])->values())->concat($subjectsWithoutCo->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - CO setup missing',
                'status' => 'OBE gap',
                'metric_keys' => ['course_outcome_gaps'],
                'action' => route('academic.obe.co.index', ['program_id' => $subject->program_id, 'subject_id' => $subject->id]),
            ])->values())->concat($lowFeedback->map(fn ($row) => [
                'title' => $row->subject?->name ?? 'Subject',
                'subtitle' => ($row->subject?->program?->code ?? 'Program') . ' - average rating ' . round($row->avg_rating, 1),
                'status' => 'Feedback action due',
                'metric_keys' => ['low_feedback_subjects'],
                'action' => route('academics.program-leadership.quality-signals'),
            ])->values())->values(),
        ];
    }

    public function reports(User $user): array
    {
        return [
            'program_portfolio' => ['label' => 'Program portfolio', 'count' => $this->programPortfolio($user)['metrics']['active_programs'], 'route' => route('academics.program-leadership.portfolio')],
            'course_delivery' => ['label' => 'Course delivery', 'count' => $this->courseDelivery($user)['metrics']['faculty_gaps'], 'route' => route('academics.program-leadership.course-delivery')],
            'student_success' => ['label' => 'Student success', 'count' => $this->studentSuccess($user)['metrics']['attendance_risk'], 'route' => route('academics.program-leadership.student-success')],
            'quality_signals' => ['label' => 'Quality signals', 'count' => $this->qualitySignals($user)['metrics']['course_outcome_gaps'], 'route' => route('academics.program-leadership.quality-signals')],
        ];
    }

    public function section(User $user, string $section, array $filters = []): array
    {
        $data = match ($section) {
            'portfolio' => $this->programPortfolio($user),
            'course-delivery' => $this->courseDelivery($user),
            'student-success' => $this->studentSuccess($user, ($filters['metric'] ?? null) === 'active_students'),
            'quality-signals' => $this->qualitySignals($user),
            default => abort(404),
        };

        $data['items'] = $this->filterItems($data['items'], $filters)->values();
        $data['filters'] = $filters;
        $data['filter_summary'] = $this->filterSummary($filters);

        return $data;
    }

    private function filterItems(Collection $items, array $filters): Collection
    {
        return $items
            ->when(! empty($filters['metric']), function (Collection $collection) use ($filters) {
                $metric = (string) $filters['metric'];

                return $collection->filter(fn (array $item) => in_array($metric, $item['metric_keys'] ?? [], true));
            })
            ->when(! empty($filters['search']), function (Collection $collection) use ($filters) {
                $search = mb_strtolower((string) $filters['search']);

                return $collection->filter(fn (array $item) => str_contains(mb_strtolower($item['title'] ?? ''), $search)
                    || str_contains(mb_strtolower($item['subtitle'] ?? ''), $search)
                    || str_contains(mb_strtolower($item['status'] ?? ''), $search));
            })
            ->when(! empty($filters['status']), function (Collection $collection) use ($filters) {
                $status = mb_strtolower((string) $filters['status']);

                return $collection->filter(fn (array $item) => mb_strtolower($item['status'] ?? '') === $status);
            });
    }

    private function filterSummary(array $filters): string
    {
        $active = collect($filters)
            ->only(['metric', 'search', 'status'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => str($key)->headline() . ': ' . $value);

        return $active->isEmpty() ? 'Showing all scoped program leadership records.' : $active->join(' | ');
    }

    private function visibleProgramIds(User $user): ?Collection
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return null;
        }

        $ids = $this->scopes->scopeIdsFor($user, 'program');
        if ($ids->isEmpty()) {
            $ids = $this->scopes->scopeIdsFor($user, 'batch')
                ->map(fn ($batchId) => Batch::whereKey($batchId)->value('program_id'))
                ->filter()
                ->unique()
                ->values();
        }

        if ($ids->isEmpty()) {
            $ids = $this->scopes->scopeIdsFor($user, 'term')
                ->map(fn ($termId) => Term::whereKey($termId)->value('program_id'))
                ->filter()
                ->unique()
                ->values();
        }

        if ($ids->isEmpty()) {
            $ids = $this->scopes->scopeIdsFor($user, 'subject')
                ->map(fn ($subjectId) => Subject::whereKey($subjectId)->value('program_id'))
                ->filter()
                ->unique()
                ->values();
        }

        return $ids;
    }

    private function applyProgramScope(Builder $query, ?Collection $programIds, string $column = 'program_id'): Builder
    {
        if ($programIds === null) {
            return $query;
        }

        if ($programIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $programIds);
    }

    private function scopeSummary(User $user): array
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return ['label' => 'All assigned programs', 'detail' => 'Department-level program leadership visibility'];
        }

        $scopes = $this->scopes->scopesFor($user);

        return [
            'label' => $scopes->pluck('scope_type')->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Assigned program work',
            'detail' => $scopes->take(4)->pluck('scope_name')->join(', ') ?: 'No explicit program scope assigned yet',
        ];
    }

    private function publishedTimetableScope(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function (Builder $versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn (Builder $version) => $version->where('status', 'published'));
            });
    }

    private function unpublishedTimetableScope(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $statusQuery) {
                $statusQuery->where('status', '!=', 'published')
                    ->orWhere(function (Builder $versionQuery) {
                        $versionQuery->where('status', 'published')
                            ->whereNotNull('timetable_version_id')
                            ->whereDoesntHave('version', fn (Builder $version) => $version->where('status', 'published'));
                    });
            });
    }

    private function studentLabel(?Student $student, mixed $fallbackId): string
    {
        if (! $student) {
            return 'Unassigned student';
        }

        return $student->user?->name
            ?? $student->enrollment_number
            ?? $student->roll_number
            ?? 'Student record ' . $fallbackId;
    }
}
