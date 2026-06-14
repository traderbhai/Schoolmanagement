<?php

namespace App\Services;

use App\Models\AcademicTranscript;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\CourseFeedback;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\DepartmentActivityLog;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\LeaveApplication;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicAttentionService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function commandCenter(User $user, ?string $workspace = null): array
    {
        $queues = collect($this->queuesFor($user, $workspace));
        $members = $this->hierarchy->members();
        $activity = DepartmentActivityLog::with(['actor', 'target'])
            ->where('department_id', $this->hierarchy->department()->id)
            ->latest()
            ->limit(12)
            ->get();

        return [
            'workspace' => $workspace ?: $this->defaultWorkspace($user),
            'scopeSummary' => $this->scopeSummary($user),
            'queues' => $queues,
            'branchSummary' => $this->branchSummary($queues),
            'kpis' => [
                'active_members' => $members->count(),
                'visible_users' => $this->hierarchy->canSeeAll($user) ? $members->pluck('user_id')->unique()->count() : $this->hierarchy->visibleUserIds($user)->count(),
                'active_scopes' => $this->scopes->scopesFor($user)->count(),
                'open_items' => $queues->sum('count'),
            ],
            'activity' => $activity,
        ];
    }

    public function queue(User $user, string $queueKey): array
    {
        return collect($this->queuesFor($user))->firstWhere('key', $queueKey)
            ?? $this->emptyQueue($queueKey, 'Unknown Queue', 'No queue definition exists for this key.', 'governance');
    }

    public function queuesFor(User $user, ?string $workspace = null): array
    {
        $workspace = $workspace ?: $this->defaultWorkspace($user);
        $programIds = $this->visibleProgramIds($user);

        $allQueues = [
            $this->pendingApprovals($programIds),
            $this->overdueApprovals($programIds),
            $this->curriculumChanges($programIds),
            $this->subjectsWithoutFaculty($programIds),
            $this->draftTimetable($programIds),
            $this->facultyWorkload($programIds),
            $this->attendanceRisk($programIds),
            $this->pendingLeaves($programIds),
            $this->examMarksPending($programIds),
            $this->resultPublishPending($programIds),
            $this->transcriptsPending($programIds),
            $this->obeMappingGaps($programIds),
            $this->feedbackPending($programIds),
        ];

        return collect($allQueues)
            ->filter(fn (array $queue) => $workspace === 'command' || $queue['workspace'] === $workspace || in_array($queue['workspace'], $this->workspaceAliases($workspace), true))
            ->values()
            ->all();
    }

    public function defaultWorkspace(User $user): string
    {
        if ($user->hasAnyRole(['exam_cell', 'coe', 'exam_manager', 'exam_officer'])) {
            return 'coe';
        }
        if ($user->hasAnyRole(['iqac_head', 'iqac_manager', 'iqac_officer'])) {
            return 'iqac';
        }
        if ($user->hasAnyRole(['program_chair', 'hod', 'pmc_head', 'pmc_manager', 'pmc_officer', 'program_director', 'program_leader', 'semester_coordinator', 'course_coordinator', 'faculty_mentor'])) {
            return 'pmc';
        }

        return 'command';
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

    private function pendingApprovals(?Collection $programIds): array
    {
        $query = ApprovalWorkflow::query()->where('status', 'pending');

        return $this->queuePayload('pending_approvals', 'Pending academic approvals', 'Approvals awaiting Dean, PMC, HoD, or CoE action.', 'governance', 'high', $query, function ($row) {
            return [
                'title' => ucfirst($row->approver_role) . ' approval',
                'subtitle' => class_basename($row->approvable_type) . ' #' . $row->approvable_id,
                'status' => $row->due_at?->isPast() ? 'Overdue' : 'Pending',
                'due' => $row->due_at?->toDateString(),
            ];
        });
    }

    private function overdueApprovals(?Collection $programIds): array
    {
        $query = ApprovalWorkflow::query()->where('status', 'pending')->whereNotNull('due_at')->where('due_at', '<', now());

        return $this->queuePayload('overdue_approvals', 'Overdue approvals', 'SLA-breached academic approval items.', 'governance', 'critical', $query);
    }

    private function curriculumChanges(?Collection $programIds): array
    {
        $query = $this->applyProgramScope(CurriculumChange::with(['program', 'subject'])->whereIn('status', ['submitted', 'under_review']), $programIds);

        return $this->queuePayload('curriculum_changes', 'Curriculum changes pending', 'Submitted curriculum changes that need academic review.', 'pmc', 'high', $query, fn ($row) => [
            'title' => $row->title,
            'subtitle' => ($row->program?->code ?? 'Program') . ' - ' . ($row->subject?->code ?? 'Program level'),
            'status' => ucfirst(str_replace('_', ' ', $row->status)),
            'due' => $row->submitted_at?->toDateString(),
        ]);
    }

    private function subjectsWithoutFaculty(?Collection $programIds): array
    {
        $assignedSubjectIds = SubjectFacultyAssignment::query()->pluck('subject_id');
        $query = $this->applyProgramScope(Subject::with('program')->where('is_active', true)->whereNotIn('id', $assignedSubjectIds), $programIds)
            ->orderBy('id');

        return $this->queuePayload('subjects_without_faculty', 'Subjects without faculty', 'Active subjects that do not yet have a faculty assignment.', 'pmc', 'critical', $query, fn ($row) => [
            'title' => $row->name,
            'subtitle' => ($row->program?->code ?? 'No program') . ' - ' . $row->code,
            'status' => 'Unassigned',
            'due' => null,
        ]);
    }

    private function draftTimetable(?Collection $programIds): array
    {
        $query = $this->applyProgramScope(TimetableEntry::with(['program', 'subject'])->where('is_active', true)->where('status', '!=', 'published'), $programIds);

        return $this->queuePayload('draft_timetable', 'Timetable not published', 'Timetable entries still in draft or archived state.', 'pmc', 'medium', $query, fn ($row) => [
            'title' => $row->subject?->name ?? 'Timetable entry',
            'subtitle' => ($row->program?->code ?? 'Program') . ' - ' . $row->day_name,
            'status' => ucfirst($row->status ?? 'draft'),
            'due' => null,
        ]);
    }

    private function facultyWorkload(?Collection $programIds): array
    {
        $query = $this->applyProgramScope(TimetableEntry::query()->selectRaw('teacher_id, count(*) as load_count')->where('is_active', true)->groupBy('teacher_id')->having('load_count', '>=', 5), $programIds);

        return $this->queuePayload('faculty_workload', 'Faculty workload imbalance', 'Faculty with heavy timetable load requiring review.', 'pmc', 'medium', $query, fn ($row) => [
            'title' => 'Teacher #' . $row->teacher_id,
            'subtitle' => $row->load_count . ' timetable slots',
            'status' => 'Review load',
            'due' => null,
        ]);
    }

    private function attendanceRisk(?Collection $programIds): array
    {
        $studentIds = Student::query();
        $this->applyProgramScope($studentIds, $programIds);
        $query = Attendance::query()
            ->selectRaw('student_id, count(*) as absence_count')
            ->whereIn('status', ['absent', 'late'])
            ->whereIn('student_id', $studentIds->pluck('id'))
            ->groupBy('student_id')
            ->having('absence_count', '>=', 2);

        return $this->queuePayload('attendance_risk', 'At-risk students', 'Students with repeated absent or late attendance signals.', 'pmc', 'high', $query, fn ($row) => [
            'title' => 'Student #' . $row->student_id,
            'subtitle' => $row->absence_count . ' attendance exceptions',
            'status' => 'Intervention due',
            'due' => null,
        ]);
    }

    private function pendingLeaves(?Collection $programIds): array
    {
        $studentIds = Student::query();
        $this->applyProgramScope($studentIds, $programIds);
        $query = LeaveApplication::with('student')->where('status', 'pending')->whereIn('student_id', $studentIds->pluck('id'));

        return $this->queuePayload('pending_leaves', 'Student leave approvals', 'Pending student leave requests in academic scope.', 'pmc', 'medium', $query, fn ($row) => [
            'title' => $row->student?->name ?? 'Student leave',
            'subtitle' => $row->leave_type . ' - ' . $row->from_date?->toDateString(),
            'status' => ucfirst($row->status),
            'due' => $row->from_date?->toDateString(),
        ]);
    }

    private function examMarksPending(?Collection $programIds): array
    {
        $examIdsWithResults = ExamResult::query()->pluck('exam_id');
        $query = $this->applyProgramScope(Exam::with(['program', 'subject'])->where('exam_date', '<=', now()->toDateString())->whereNotIn('id', $examIdsWithResults), $programIds);

        return $this->queuePayload('exam_marks_pending', 'Exam marks pending', 'Completed exams without marks entered.', 'coe', 'critical', $query, fn ($row) => [
            'title' => $row->name,
            'subtitle' => ($row->program?->code ?? 'Program') . ' - ' . ($row->subject?->code ?? 'Subject'),
            'status' => 'Marks pending',
            'due' => $row->exam_date?->toDateString(),
        ]);
    }

    private function resultPublishPending(?Collection $programIds): array
    {
        $query = $this->applyProgramScope(Exam::with(['program', 'subject'])->whereHas('results')->where('exam_date', '<=', now()->toDateString()), $programIds);

        return $this->queuePayload('result_publish_pending', 'Result publish pending', 'Exams with marks present and result publishing still requiring CoE review.', 'coe', 'high', $query, fn ($row) => [
            'title' => $row->name,
            'subtitle' => ($row->program?->code ?? 'Program') . ' - ' . ($row->subject?->code ?? 'Subject'),
            'status' => 'Publish review',
            'due' => $row->exam_date?->toDateString(),
        ]);
    }

    private function transcriptsPending(?Collection $programIds): array
    {
        $studentIds = Student::query();
        $this->applyProgramScope($studentIds, $programIds);
        $query = AcademicTranscript::with('student')->where('status', 'draft')->whereIn('student_id', $studentIds->pluck('id'));

        return $this->queuePayload('transcripts_pending', 'Transcript drafts pending', 'Draft transcripts waiting for CoE issue/archive action.', 'coe', 'medium', $query, fn ($row) => [
            'title' => $row->student?->name ?? 'Transcript',
            'subtitle' => $row->academic_year ?? 'Academic year',
            'status' => ucfirst($row->status),
            'due' => null,
        ]);
    }

    private function obeMappingGaps(?Collection $programIds): array
    {
        $programsWithoutPo = Program::where('is_active', true)->whereNotIn('id', ProgramOutcome::query()->pluck('program_id'));
        $this->applyProgramScope($programsWithoutPo, $programIds);

        $subjectsWithoutCo = Subject::with('program')->where('is_active', true)->whereNotIn('id', CourseOutcome::query()->pluck('subject_id'));
        $this->applyProgramScope($subjectsWithoutCo, $programIds);

        $items = collect($programsWithoutPo->get()->map(fn ($program) => [
            'title' => $program->name,
            'subtitle' => 'Program outcomes missing',
            'status' => 'OBE gap',
            'due' => null,
        ])->values())->merge($subjectsWithoutCo->limit(20)->get()->map(fn ($subject) => [
            'title' => $subject->name,
            'subtitle' => 'Course outcomes missing - ' . ($subject->program?->code ?? 'Program'),
            'status' => 'OBE gap',
            'due' => null,
        ]));

        return [
            'key' => 'obe_mapping_gaps',
            'title' => 'OBE mapping gaps',
            'description' => 'Programs or subjects missing PO/CO mapping setup.',
            'workspace' => 'iqac',
            'severity' => 'high',
            'count' => $items->count(),
            'items' => $items->take(10)->values(),
            'route' => route('academics.attention.queue', ['queue' => 'obe_mapping_gaps']),
        ];
    }

    private function feedbackPending(?Collection $programIds): array
    {
        $subjectsWithFeedback = CourseFeedback::query()->pluck('subject_id');
        $query = $this->applyProgramScope(Subject::with('program')->where('is_active', true)->whereNotIn('id', $subjectsWithFeedback), $programIds);

        return $this->queuePayload('feedback_pending', 'Feedback collection pending', 'Subjects without current course feedback records.', 'iqac', 'medium', $query, fn ($row) => [
            'title' => $row->name,
            'subtitle' => ($row->program?->code ?? 'Program') . ' - ' . $row->code,
            'status' => 'Feedback pending',
            'due' => null,
        ]);
    }

    private function queuePayload(string $key, string $title, string $description, string $workspace, string $severity, Builder $query, ?callable $mapper = null): array
    {
        $count = (clone $query)->count();
        $items = (clone $query)->limit(10)->get()->map($mapper ?: fn ($row) => [
            'title' => class_basename($row) . ' #' . $row->id,
            'subtitle' => $description,
            'status' => $row->status ?? 'Open',
            'due' => $row->due_at?->toDateString() ?? null,
        ]);

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'workspace' => $workspace,
            'severity' => $severity,
            'count' => $count,
            'items' => $items,
            'route' => route('academics.attention.queue', ['queue' => $key]),
        ];
    }

    private function branchSummary(Collection $queues): array
    {
        return [
            'governance' => $queues->where('workspace', 'governance')->sum('count'),
            'pmc' => $queues->where('workspace', 'pmc')->sum('count'),
            'coe' => $queues->where('workspace', 'coe')->sum('count'),
            'iqac' => $queues->where('workspace', 'iqac')->sum('count'),
        ];
    }

    private function scopeSummary(User $user): array
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return ['label' => 'All Academics', 'detail' => 'Full department visibility'];
        }

        $scopes = $this->scopes->scopesFor($user);

        return [
            'label' => $scopes->pluck('scope_type')->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Assigned work',
            'detail' => $scopes->take(4)->pluck('scope_name')->join(', ') ?: 'No explicit academic scope assigned yet',
        ];
    }

    private function workspaceAliases(string $workspace): array
    {
        return match ($workspace) {
            'dean', 'command' => ['governance', 'pmc', 'coe', 'iqac'],
            'program' => ['pmc'],
            default => [],
        };
    }

    private function emptyQueue(string $key, string $title, string $description, string $workspace): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'workspace' => $workspace,
            'severity' => 'low',
            'count' => 0,
            'items' => collect(),
            'route' => route('academics.attention.queue', ['queue' => $key]),
        ];
    }
}
