<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CurriculumChange;
use App\Models\ExamResult;
use App\Models\LeaveApplication;
use App\Models\Program;
use App\Models\ProgramSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicPmcOperatingService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function dashboard(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $curriculum = $this->curriculumReadiness($user);
        $faculty = $this->facultyAllocation($user);
        $timetable = $this->timetableReadiness($user);
        $students = $this->studentMonitoring($user);

        return [
            'scopeSummary' => $this->scopeSummary($user),
            'kpis' => [
                'programs' => $this->scopedPrograms($user)['metrics']['active_programs'],
                'curriculum_gaps' => $curriculum['metrics']['mapping_gaps'] + $curriculum['metrics']['pending_changes'],
                'faculty_gaps' => $faculty['metrics']['unassigned_subjects'] + $faculty['metrics']['overloaded_faculty'],
                'student_risk' => $students['metrics']['attendance_risk'] + $students['metrics']['weak_performance'],
            ],
            'curriculum' => $curriculum,
            'faculty' => $faculty,
            'timetable' => $timetable,
            'students' => $students,
            'reports' => $this->reports($user),
        ];
    }

    public function scopedPrograms(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $programs = $this->applyProgramScope(
            Program::withCount(['students', 'subjects'])->where('is_active', true),
            $programIds,
            'id'
        )->orderBy('name')->limit(100)->get();

        return [
            'title' => 'Scoped Programs',
            'description' => 'Programs currently visible to PMC for planning, curriculum, timetable, delivery, and student monitoring.',
            'metrics' => [
                'active_programs' => $programs->count(),
                'student_strength' => $programs->sum('students_count'),
                'subject_coverage' => $programs->sum('subjects_count'),
            ],
            'items' => $programs->map(fn (Program $program) => [
                'title' => $program->name,
                'subtitle' => trim(($program->code ?: 'Program') . ' - ' . $program->students_count . ' students - ' . $program->subjects_count . ' subjects'),
                'status' => 'Active program',
                'metric_keys' => ['active_programs', 'programs'],
                'action' => route('admin.programs.show', $program),
            ])->values(),
        ];
    }

    public function curriculumReadiness(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $subjectIdsMapped = ProgramSubject::query()->where('is_active', true)->pluck('subject_id');
        $mappingGaps = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', $subjectIdsMapped),
            $programIds
        )->orderBy('name')->limit(25)->get();

        $pendingChanges = $this->applyProgramScope(
            CurriculumChange::with(['program', 'subject'])->whereIn('status', ['submitted', 'under_review']),
            $programIds
        )->latest('submitted_at')->limit(25)->get();

        return [
            'title' => 'Curriculum Readiness',
            'description' => 'Program structure, subject mapping, credits, and curriculum change approvals.',
            'metrics' => [
                'mapping_gaps' => $mappingGaps->count(),
                'pending_changes' => $pendingChanges->count(),
                'active_subjects' => $this->applyProgramScope(Subject::where('is_active', true), $programIds)->count(),
                'mapped_subjects' => $this->applyProgramScope(ProgramSubject::where('is_active', true), $programIds)->count(),
            ],
            'items' => $mappingGaps->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - ' . $subject->code,
                'status' => 'Mapping missing',
                'metric_keys' => ['curriculum_gaps', 'mapping_gaps'],
                'action' => route('academics.pmc.curriculum-governance.index'),
            ])->merge($pendingChanges->map(fn (CurriculumChange $change) => [
                'title' => $change->title,
                'subtitle' => ($change->program?->code ?? 'Program') . ' - ' . ($change->subject?->code ?? 'Program level'),
                'status' => ucfirst(str_replace('_', ' ', $change->status)),
                'metric_keys' => ['curriculum_gaps', 'pending_changes'],
                'action' => route('academics.pmc.approvals.index'),
            ]))->values(),
        ];
    }

    public function facultyAllocation(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $assignedSubjectIds = SubjectFacultyAssignment::query()->pluck('subject_id');
        $unassigned = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', $assignedSubjectIds),
            $programIds
        )->orderBy('name')->limit(25)->get();

        $loads = $this->applyProgramScope(
            SubjectFacultyAssignment::with(['teacher.user', 'program'])
                ->selectRaw('teacher_id, program_id, count(*) as subject_count')
                ->groupBy('teacher_id', 'program_id')
                ->having('subject_count', '>=', 3),
            $programIds
        )->limit(25)->get();

        return [
            'title' => 'Faculty Allocation',
            'description' => 'Primary faculty, co-faculty, workload, and allocation exceptions.',
            'metrics' => [
                'unassigned_subjects' => $unassigned->count(),
                'assigned_subjects' => $this->applyProgramScope(SubjectFacultyAssignment::query(), $programIds)->count(),
                'overloaded_faculty' => $loads->count(),
                'co_faculty_assignments' => $this->applyProgramScope(SubjectFacultyAssignment::where('is_primary', false), $programIds)->count(),
            ],
            'items' => $unassigned->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - faculty not assigned',
                'status' => 'Unassigned',
                'metric_keys' => ['faculty_gaps', 'unassigned_subjects'],
                'action' => route('academics.pmc.faculty-allocation-v004.index'),
            ])->merge($loads->map(fn ($load) => [
                'title' => $this->teacherLabel($load->teacher, $load->teacher_id),
                'subtitle' => ($load->program?->code ?? 'Program') . ' - ' . $load->subject_count . ' subjects',
                'status' => 'Workload review',
                'metric_keys' => ['faculty_gaps', 'overloaded_faculty'],
                'action' => route('academics.pmc.faculty-allocation-v004.index'),
            ]))->values(),
        ];
    }

    public function timetableReadiness(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $draft = $this->applyProgramScope(
            TimetableEntry::with(['program', 'subject', 'teacher.user', 'classroom'])
                ->where('is_active', true)
                ->where(fn (Builder $query) => $this->unpublishedTimetableScope($query)),
            $programIds
        )->orderBy('day_of_week')->limit(25)->get();

        $conflicts = $this->applyProgramScope(
            TimetableEntry::query()
                ->selectRaw('teacher_id, day_of_week, timetable_slot_id, count(*) as conflict_count')
                ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
                ->groupBy('teacher_id', 'day_of_week', 'timetable_slot_id')
                ->having('conflict_count', '>', 1),
            $programIds
        )->limit(25)->get();
        $teacherMap = Teacher::with('user')
            ->whereIn('id', $conflicts->pluck('teacher_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return [
            'title' => 'Timetable Readiness',
            'description' => 'Draft slots, publish readiness, faculty-room conflicts, and workload balance.',
            'metrics' => [
                'draft_slots' => $draft->count(),
                'published_slots' => $this->applyProgramScope(TimetableEntry::where(fn (Builder $query) => $this->publishedTimetableScope($query)), $programIds)->count(),
                'teacher_conflicts' => $conflicts->count(),
                'active_slots' => $this->applyProgramScope(TimetableEntry::where('is_active', true), $programIds)->count(),
            ],
            'items' => $draft->map(fn (TimetableEntry $entry) => [
                'title' => $entry->subject?->name ?? 'Timetable slot',
                'subtitle' => ($entry->program?->code ?? 'Program') . ' - ' . $entry->day_name . ' - ' . ($entry->classroom?->name ?? 'Room pending'),
                'status' => ucfirst($entry->status ?? 'draft'),
                'metric_keys' => ['draft_slots'],
                'action' => route('academics.pmc.timetable-planner.index'),
            ])->merge($conflicts->map(fn ($conflict) => [
                'title' => $this->teacherLabel($teacherMap->get($conflict->teacher_id), $conflict->teacher_id) . ' conflict',
                'subtitle' => 'Day ' . $conflict->day_of_week . ', slot ' . ($conflict->timetable_slot_id ?: 'pending'),
                'status' => 'Conflict',
                'metric_keys' => ['teacher_conflicts'],
                'action' => route('academics.pmc.timetable-planner.index'),
            ]))->values(),
        ];
    }

    public function studentMonitoring(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $visibleStudentIds = $this->applyProgramScope(Student::query(), $programIds)->pluck('id');

        $attendanceRisk = Attendance::query()
            ->with(['student.user', 'student.program'])
            ->selectRaw('student_id, count(*) as exception_count')
            ->whereHas('timetableEntry', fn (Builder $query) => $this->publishedTimetableScope($query))
            ->whereIn('student_id', $visibleStudentIds)
            ->whereIn('status', ['absent', 'late'])
            ->groupBy('student_id')
            ->having('exception_count', '>=', 2)
            ->limit(25)
            ->get();

        $weakPerformance = ExamResult::with(['student.user', 'student.program', 'exam.subject'])
            ->whereIn('student_id', $visibleStudentIds)
            ->whereHas('exam', fn (Builder $query) => $query
                ->whereNotNull('published_at')
                ->whereColumn('exam_results.marks_obtained', '<', 'exams.passing_marks'))
            ->limit(25)
            ->get();

        $pendingLeaves = LeaveApplication::with(['student.user', 'student.program'])
            ->where('status', 'pending')
            ->whereIn('student_id', $visibleStudentIds)
            ->limit(25)
            ->get();

        return [
            'title' => 'Student Monitoring',
            'description' => 'Attendance risk, weak performance, leave approvals, and mentoring interventions.',
            'metrics' => [
                'attendance_risk' => $attendanceRisk->count(),
                'weak_performance' => $weakPerformance->count(),
                'pending_leaves' => $pendingLeaves->count(),
                'unassigned_mentors' => $this->applyProgramScope(Student::whereNull('mentor_id'), $programIds)->count(),
            ],
            'items' => $attendanceRisk->map(fn ($row) => [
                'title' => $this->studentLabel($row->student, $row->student_id),
                'subtitle' => ($row->student?->program?->code ?? 'Program') . ' - ' . $row->exception_count . ' attendance exceptions',
                'status' => 'Intervention due',
                'metric_keys' => ['student_risk', 'attendance_risk'],
                'action' => route('academics.pmc.student-success-v004.index'),
            ])->merge($weakPerformance->map(fn (ExamResult $result) => [
                'title' => $this->studentLabel($result->student, $result->student_id),
                'subtitle' => ($result->exam?->subject?->code ?? 'Exam') . ' - ' . $result->marks_obtained . '/' . $result->exam?->passing_marks,
                'status' => 'Weak performance',
                'metric_keys' => ['student_risk', 'weak_performance'],
                'action' => route('academics.pmc.student-success-v004.index'),
            ]))->merge($pendingLeaves->map(fn (LeaveApplication $leave) => [
                'title' => $this->studentLabel($leave->student, $leave->student_id),
                'subtitle' => $leave->leave_type . ' from ' . $leave->from_date?->toDateString(),
                'status' => 'Leave pending',
                'metric_keys' => ['pending_leaves'],
                'action' => route('academics.pmc.student-success-v004.index'),
            ]))->values(),
        ];
    }

    public function reports(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);

        return [
            'curriculum_readiness' => [
                'label' => 'Curriculum readiness',
                'count' => $this->curriculumReadiness($user)['metrics']['mapping_gaps'],
                'route' => route('academics.pmc.curriculum-readiness'),
            ],
            'timetable_readiness' => [
                'label' => 'Timetable readiness',
                'count' => $this->timetableReadiness($user)['metrics']['draft_slots'],
                'route' => route('academics.pmc.timetable-readiness'),
            ],
            'faculty_workload' => [
                'label' => 'Faculty workload',
                'count' => $this->facultyAllocation($user)['metrics']['overloaded_faculty'],
                'route' => route('academics.pmc.faculty-allocation'),
            ],
            'student_risk' => [
                'label' => 'Student risk',
                'count' => $this->studentMonitoring($user)['metrics']['attendance_risk'],
                'route' => route('academics.pmc.student-monitoring'),
            ],
            'active_programs' => [
                'label' => 'Active scoped programs',
                'count' => $this->applyProgramScope(Program::where('is_active', true), $programIds)->count(),
                'route' => route('academics.pmc.programs', ['metric' => 'active_programs']),
            ],
        ];
    }

    public function section(User $user, string $section, array $filters = []): array
    {
        $data = match ($section) {
            'programs' => $this->scopedPrograms($user),
            'curriculum-readiness' => $this->curriculumReadiness($user),
            'faculty-allocation' => $this->facultyAllocation($user),
            'timetable-readiness' => $this->timetableReadiness($user),
            'student-monitoring' => $this->studentMonitoring($user),
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

        return $active->isEmpty() ? 'Showing all scoped PMC records.' : $active->join(' | ');
    }

    private function visibleProgramIds(User $user): ?Collection
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return null;
        }

        $ids = $this->scopes->scopeIdsFor($user, 'program');
        if ($ids->isEmpty()) {
            $ids = $this->scopes->scopeIdsFor($user, 'batch')
                ->map(fn ($batchId) => \App\Models\Batch::whereKey($batchId)->value('program_id'))
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
            return ['label' => 'All PMC programs', 'detail' => 'Department-level visibility'];
        }

        $scopes = $this->scopes->scopesFor($user);

        return [
            'label' => $scopes->pluck('scope_type')->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Assigned PMC work',
            'detail' => $scopes->take(4)->pluck('scope_name')->join(', ') ?: 'No explicit PMC program scope assigned yet',
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

    private function teacherLabel(?Teacher $teacher, mixed $fallbackId): string
    {
        if (! $teacher) {
            return 'Unassigned faculty';
        }

        return $teacher->user?->name
            ?? $teacher->employee_id
            ?? 'Faculty record ' . $fallbackId;
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
