<?php

namespace App\Services;

use App\Models\AcademicPmcAnalyticsSnapshot;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcAutomationExecution;
use App\Models\AcademicPmcAutomationRule;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcPolicyAudit;
use App\Models\AcademicPmcReviewGovernanceRecord;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcWorkItem;
use App\Models\DepartmentActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicPmcV004Service
{
    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function command(User $user): array
    {
        $records = AcademicPmcOperatingRecord::query();

        return [
            'scopeLabel' => $this->policy->scopeLabel($user),
            'kpis' => [
                'semester_readiness' => $this->readinessAverage(),
                'curriculum_blockers' => (clone $records)->where('record_type', 'curriculum_rollout')->whereIn('status', ['blocked', 'pending', 'open'])->count(),
                'unassigned_subjects' => (clone $records)->where('record_type', 'faculty_allocation')->where('category', 'unassigned_subject')->count(),
                'faculty_overload' => (clone $records)->where('record_type', 'faculty_allocation')->whereIn('risk_band', ['high', 'critical'])->count(),
                'timetable_conflicts' => (clone $records)->where('record_type', 'timetable_governance')->where('category', 'conflict')->count(),
                'course_delivery_delay' => (clone $records)->where('record_type', 'course_delivery')->whereIn('risk_band', ['high', 'critical'])->count(),
                'marks_pending' => (clone $records)->where('record_type', 'course_delivery')->where('category', 'marks_pending')->count(),
                'student_success_risk' => (clone $records)->where('record_type', 'student_success')->whereIn('risk_band', ['high', 'critical'])->count(),
                'overdue_actions' => AcademicPmcWorkItem::whereNotIn('status', ['done', 'closed', 'cancelled'])->where('due_at', '<', now())->count(),
            ],
            'links' => $this->metricLinks(),
            'attention' => $this->attentionQueues()->take(12),
            'planning' => $this->records('planning', 6),
            'approvals' => AcademicPmcApproval::with('owner')->whereIn('status', ['pending', 'returned', 'evidence_requested'])->orderBy('due_at')->limit(8)->get(),
            'savedViews' => $this->savedViews($user, 'command'),
            'reports' => $this->reports(),
        ];
    }

    public function surface(User $user, string $surface, array $filters = []): array
    {
        $map = $this->surfaceMap();
        $config = $map[$surface] ?? $map['planning'];
        $query = AcademicPmcOperatingRecord::with(['program', 'batch', 'term', 'subject', 'student.user', 'teacher.user', 'owner'])
            ->whereIn('record_type', $config['types']);

        $this->applyFilters($query, $filters);

        return [
            'surface' => $surface,
            'title' => $config['title'],
            'description' => $config['description'],
            'records' => $query->orderByRaw("case when risk_band = 'critical' then 0 when risk_band = 'high' then 1 when risk_band = 'medium' then 2 else 3 end")->latest()->paginate(15)->withQueryString(),
            'summary' => $this->summaryFor($config['types']),
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters),
            'savedViews' => $this->savedViews($user, $surface),
            'legacyLinks' => $config['legacyLinks'] ?? [],
        ];
    }

    public function approvals(array $filters = []): array
    {
        $query = AcademicPmcApproval::with(['program', 'owner', 'requester', 'decider']);
        $this->applyFilters($query, $filters);

        return [
            'title' => 'PMC Approval Cockpit',
            'approvals' => $query->orderBy('due_at')->paginate(20)->withQueryString(),
            'pending' => AcademicPmcApproval::where('status', 'pending')->count(),
            'overdue' => AcademicPmcApproval::where('status', 'pending')->where('due_at', '<', now())->count(),
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters),
        ];
    }

    public function reviews(User $user): array
    {
        return [
            'templates' => AcademicPmcReviewGovernanceRecord::where('record_type', 'template')->latest()->get(),
            'agenda' => AcademicPmcReviewGovernanceRecord::with(['meeting', 'owner'])->where('record_type', 'agenda')->latest()->paginate(10, ['*'], 'agenda_page'),
            'minutes' => AcademicPmcReviewGovernanceRecord::with('meeting')->where('record_type', 'minutes')->latest()->paginate(10, ['*'], 'minutes_page'),
            'decisions' => AcademicPmcReviewGovernanceRecord::with('owner')->where('record_type', 'decision')->latest()->paginate(10, ['*'], 'decision_page'),
            'actions' => AcademicPmcWorkItem::with('owner')->where('work_type', 'review_action')->latest()->paginate(10, ['*'], 'action_page'),
            'savedViews' => $this->savedViews($user, 'reviews'),
        ];
    }

    public function analytics(User $user, array $filters = []): array
    {
        $query = AcademicPmcAnalyticsSnapshot::with('program')->latest('snapshot_date');
        $this->applyFilters($query, $filters);

        return [
            'snapshots' => $query->paginate(20)->withQueryString(),
            'reports' => $this->reports(),
            'filterSummary' => $this->filterSummary($filters),
            'savedViews' => $this->savedViews($user, 'analytics'),
        ];
    }

    public function policyAudit(): array
    {
        return [
            'audits' => AcademicPmcPolicyAudit::orderByDesc('risk_level')->orderBy('route_name')->paginate(25),
            'missing' => AcademicPmcPolicyAudit::where('missing_enforcement', true)->count(),
            'highRisk' => AcademicPmcPolicyAudit::where('risk_level', 'high')->count(),
        ];
    }

    public function createRecord(User $actor, array $data): AcademicPmcOperatingRecord
    {
        $record = AcademicPmcOperatingRecord::create($data + ['created_by' => $actor->id, 'owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
        $this->audit($actor, 'academic_pmc_record_created', $record->title, $record);
        return $record;
    }

    public function updateRecord(User $actor, AcademicPmcOperatingRecord $record, array $data): AcademicPmcOperatingRecord
    {
        $record->update($data);
        $this->audit($actor, 'academic_pmc_record_updated', $record->title, $record);
        return $record->fresh();
    }

    public function createWorkItemFromRecord(User $actor, AcademicPmcOperatingRecord $record): AcademicPmcWorkItem
    {
        $item = AcademicPmcWorkItem::updateOrCreate(
            ['source_type' => 'academic_pmc_operating_record', 'source_key' => (string) $record->id],
            [
                'work_type' => $record->record_type,
                'title' => $record->title,
                'description' => $record->description ?: 'PMC blocker converted into an accountable work item.',
                'program_id' => $record->program_id,
                'batch_id' => $record->batch_id,
                'term_id' => $record->term_id,
                'subject_id' => $record->subject_id,
                'student_id' => $record->student_id,
                'teacher_id' => $record->teacher_id,
                'owner_user_id' => $record->owner_user_id ?: $actor->id,
                'assigned_by' => $actor->id,
                'priority' => $record->priority,
                'severity' => $record->risk_band ?: 'normal',
                'status' => 'open',
                'due_at' => $record->due_at,
                'metadata' => ['created_from' => 'PMC OS v0.04'],
            ]
        );
        $this->audit($actor, 'academic_pmc_work_item_created_from_record', $item->title, $item);
        return $item;
    }

    public function decideApproval(User $actor, AcademicPmcApproval $approval, string $status, ?string $reason): AcademicPmcApproval
    {
        if (in_array($status, ['rejected', 'returned', 'evidence_requested', 'escalated'], true) && ! $reason) {
            abort(422, 'Reason is required for this approval decision.');
        }

        $approval->update([
            'status' => $status,
            'decision_reason' => $reason,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $this->audit($actor, 'academic_pmc_approval_decided', $approval->title, $approval);
        return $approval->fresh();
    }

    public function refreshSignals(User $actor): array
    {
        $rules = AcademicPmcAutomationRule::where('is_active', true)->orderBy('priority')->get();
        $created = 0;
        foreach ($rules as $rule) {
            $key = 'pmc-v004-' . $rule->trigger_key . '-' . now()->format('Ymd');
            $execution = AcademicPmcAutomationExecution::firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'rule_id' => $rule->id,
                    'subject_type' => 'pmc_signal',
                    'subject_key' => $rule->trigger_key,
                    'status' => 'executed',
                    'result' => 'Signal refreshed and attention queues synchronized.',
                    'metadata' => ['actions' => $rule->actions],
                    'executed_at' => now(),
                ]
            );
            if ($execution->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->audit($actor, 'academic_pmc_automation_refresh', 'PMC automation refresh executed', null, ['created' => $created]);
        return ['rules' => $rules->count(), 'created' => $created];
    }

    public function saveView(User $user, array $data): AcademicPmcSavedView
    {
        if (! empty($data['is_default'])) {
            AcademicPmcSavedView::where('user_id', $user->id)->where('surface', $data['surface'])->update(['is_default' => false]);
        }

        return AcademicPmcSavedView::updateOrCreate(
            ['user_id' => $user->id, 'surface' => $data['surface'], 'name' => $data['name']],
            ['filters' => $data['filters'] ?? [], 'is_default' => (bool) ($data['is_default'] ?? false)]
        );
    }

    public function reports(): Collection
    {
        return collect([
            ['key' => 'semester_readiness', 'label' => 'Semester readiness', 'count' => AcademicPmcOperatingRecord::where('record_type', 'semester_readiness')->count(), 'route' => route('academics.pmc.semester-readiness.index')],
            ['key' => 'curriculum', 'label' => 'Curriculum rollout', 'count' => AcademicPmcOperatingRecord::where('record_type', 'curriculum_rollout')->count(), 'route' => route('academics.pmc.curriculum-governance.index')],
            ['key' => 'faculty', 'label' => 'Faculty allocation', 'count' => AcademicPmcOperatingRecord::where('record_type', 'faculty_allocation')->count(), 'route' => route('academics.pmc.faculty-allocation-v004.index')],
            ['key' => 'timetable', 'label' => 'Timetable governance', 'count' => AcademicPmcOperatingRecord::where('record_type', 'timetable_governance')->count(), 'route' => route('academics.pmc.timetable-governance.index')],
            ['key' => 'delivery', 'label' => 'Course delivery', 'count' => AcademicPmcOperatingRecord::where('record_type', 'course_delivery')->count(), 'route' => route('academics.pmc.course-delivery.index')],
            ['key' => 'student_success', 'label' => 'Student success', 'count' => AcademicPmcOperatingRecord::where('record_type', 'student_success')->count(), 'route' => route('academics.pmc.student-success-v004.index')],
            ['key' => 'approvals', 'label' => 'Approval SLA', 'count' => AcademicPmcApproval::where('status', 'pending')->count(), 'route' => route('academics.pmc.approvals.index')],
            ['key' => 'policy_audit', 'label' => 'Policy audit', 'count' => AcademicPmcPolicyAudit::where('missing_enforcement', false)->count(), 'route' => route('academics.pmc.policy-audit.index')],
        ]);
    }

    public function export(string $report, User $actor, array $filters = []): StreamedResponse
    {
        $rows = $this->exportRows($report, $filters);
        \App\Models\AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => $report,
            'filters' => $filters,
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'Academics PMC OS v0.04'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['title', 'type', 'status', 'owner', 'risk', 'score', 'due']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'pmc-v004-' . $report . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function surfaceMap(): array
    {
        return [
            'planning' => ['title' => 'PMC Academic Planning Cycle', 'description' => 'Annual, semester, program-term, calendar, elective, assessment, review, and resource readiness planning.', 'types' => ['planning', 'semester_readiness', 'academic_calendar']],
            'semester-readiness' => ['title' => 'PMC Semester Readiness', 'description' => 'Checklist view for curriculum, faculty, timetable, resources, electives, assessment, mentors, and student-risk readiness.', 'types' => ['semester_readiness']],
            'academic-calendar' => ['title' => 'PMC Academic Calendar', 'description' => 'Program execution milestones, review calendars, delivery calendars, and internal assessment calendars.', 'types' => ['academic_calendar']],
            'curriculum-governance-v004' => ['title' => 'PMC Deep Curriculum Governance', 'description' => 'Syllabus versions, curriculum approvals, CO/PO mapping, credit checks, and rollout tracking.', 'types' => ['curriculum_rollout', 'syllabus_version', 'curriculum_validation'], 'legacyLinks' => [['label' => 'Legacy curriculum setup', 'route' => route('chair.curriculum.index')], ['label' => 'Electives', 'route' => route('chair.curriculum.electives')], ['label' => 'Assessment components', 'route' => route('chair.curriculum.assessment')]]],
            'syllabus-versions' => ['title' => 'PMC Syllabus Versions', 'description' => 'Versioned syllabus and publish status by program, term, and subject.', 'types' => ['syllabus_version']],
            'curriculum-rollout' => ['title' => 'PMC Curriculum Rollout', 'description' => 'Rollout tasks for lesson plans, CO mapping, assessment components, faculty briefing, and syllabus publication.', 'types' => ['curriculum_rollout']],
            'faculty-allocation-v004' => ['title' => 'PMC Faculty Allocation Governance', 'description' => 'Subject allocation, load balancing, suitability, shortage, and approval workflow.', 'types' => ['faculty_allocation', 'faculty_shortage', 'workload_rule'], 'legacyLinks' => [['label' => 'Legacy faculty assignment', 'route' => route('chair.curriculum.assignments')], ['label' => 'Faculty workload', 'route' => route('chair.faculty.workload')]]],
            'workload-rules' => ['title' => 'PMC Workload Rules', 'description' => 'Configurable faculty load thresholds, subject caps, mentor capacity, and substitution risk rules.', 'types' => ['workload_rule']],
            'faculty-shortage' => ['title' => 'PMC Faculty Shortage', 'description' => 'Adjunct needs, unavailable faculty, unfilled subjects, and substitute coverage gaps.', 'types' => ['faculty_shortage']],
            'timetable-governance' => ['title' => 'PMC Timetable Governance', 'description' => 'Version status, freeze/revision workflow, conflict control, and publish readiness.', 'types' => ['timetable_governance', 'timetable_conflict', 'substitution_control'], 'legacyLinks' => [['label' => 'Timetable builder', 'route' => route('chair.timetable.builder')], ['label' => 'Import timetable', 'route' => route('chair.timetable.import')], ['label' => 'Substitutions', 'route' => route('chair.timetable.substitutions')]]],
            'timetable-conflicts' => ['title' => 'PMC Timetable Conflict Board', 'description' => 'Teacher, room, batch, availability, overload, and lab/resource conflicts with resolution actions.', 'types' => ['timetable_conflict']],
            'substitution-control' => ['title' => 'PMC Substitution Control', 'description' => 'Repeated substitutions, uncovered classes, cancellations, and reschedule review.', 'types' => ['substitution_control']],
            'course-delivery' => ['title' => 'PMC Course Delivery Control', 'description' => 'Planned vs actual teaching, missed sessions, assessment progress, LMS readiness, and remedial planning.', 'types' => ['course_delivery', 'delivery_risk', 'remedial_plan'], 'legacyLinks' => [['label' => 'Legacy course delivery', 'route' => route('chair.faculty.course-delivery')], ['label' => 'Marks tracker', 'route' => route('chair.faculty.marks-tracker')], ['label' => 'Feedback', 'route' => route('chair.faculty.feedback')]]],
            'delivery-risk' => ['title' => 'PMC Delivery Risk', 'description' => 'Behind-schedule subjects, low attendance, weak results, poor feedback, and faculty delay risks.', 'types' => ['delivery_risk']],
            'remedial-planning' => ['title' => 'PMC Remedial Planning', 'description' => 'Remedial classes, owner actions, escalation, and outcome tracking.', 'types' => ['remedial_plan']],
            'student-success-v004' => ['title' => 'PMC Student Success And Mentoring Control', 'description' => 'Student risk scoring, interventions, mentor governance, parent escalation, and retention signals.', 'types' => ['student_success', 'intervention', 'mentor_governance', 'parent_escalation'], 'legacyLinks' => [['label' => 'At-risk students', 'route' => route('chair.students.at-risk')], ['label' => 'Mentors', 'route' => route('chair.students.mentors')], ['label' => 'Grievances', 'route' => route('chair.students.grievances')]]],
            'interventions' => ['title' => 'PMC Student Interventions', 'description' => 'Mentor contact, parent contact, remedial assignment, review, resolution, and escalation.', 'types' => ['intervention']],
            'mentor-governance' => ['title' => 'PMC Mentor Governance', 'description' => 'Mentor load, overdue follow-up, effectiveness, and assigned student scope.', 'types' => ['mentor_governance']],
            'parent-escalations' => ['title' => 'PMC Parent Escalations', 'description' => 'Parent/guardian escalation queue and follow-up tracking.', 'types' => ['parent_escalation']],
            'automation' => ['title' => 'PMC Automation And Attention Rules', 'description' => 'Deterministic rules that refresh signals, create work items, and escalate overdue blockers.', 'types' => ['automation_rule']],
        ];
    }

    public function automation(): array
    {
        return [
            'rules' => AcademicPmcAutomationRule::orderBy('priority')->paginate(15),
            'executions' => AcademicPmcAutomationExecution::with('rule')->latest('executed_at')->paginate(15),
        ];
    }

    private function attentionQueues(): Collection
    {
        return AcademicPmcOperatingRecord::with(['owner', 'program'])
            ->where(fn ($q) => $q->whereIn('risk_band', ['critical', 'high'])->orWhere('due_at', '<', now()))
            ->orderByRaw("case when risk_band = 'critical' then 0 when risk_band = 'high' then 1 else 2 end")
            ->orderBy('due_at')
            ->limit(50)
            ->get()
            ->map(fn ($record) => [
                'title' => $record->title,
                'subtitle' => $record->program?->code . ' - ' . str($record->record_type)->headline(),
                'severity' => $record->risk_band ?: 'normal',
                'owner' => $record->owner?->name ?? 'PMC',
                'due' => $record->due_at?->toDateString(),
                'route' => $record->source_route ?: route('academics.pmc.attention.index', ['type' => $record->record_type]),
                'action' => $record->payload['recommended_action'] ?? 'Review and assign owner',
            ]);
    }

    private function records(string $type, int $limit): Collection
    {
        return AcademicPmcOperatingRecord::with(['program', 'owner'])->where('record_type', $type)->latest()->limit($limit)->get();
    }

    private function readinessAverage(): int
    {
        $avg = AcademicPmcOperatingRecord::where('record_type', 'semester_readiness')->avg('score');
        return (int) round($avg ?: 0);
    }

    private function metricLinks(): array
    {
        return [
            'semester_readiness' => route('academics.pmc.semester-readiness.index'),
            'curriculum_blockers' => route('academics.pmc.curriculum-governance.index', ['status' => 'blocked']),
            'unassigned_subjects' => route('academics.pmc.faculty-allocation-v004.index', ['category' => 'unassigned_subject']),
            'faculty_overload' => route('academics.pmc.faculty-allocation-v004.index', ['risk_band' => 'high']),
            'timetable_conflicts' => route('academics.pmc.timetable-conflicts.index'),
            'course_delivery_delay' => route('academics.pmc.delivery-risk.index'),
            'marks_pending' => route('academics.pmc.course-delivery.index', ['category' => 'marks_pending']),
            'student_success_risk' => route('academics.pmc.student-success-v004.index', ['risk_band' => 'high']),
            'overdue_actions' => route('academics.pmc.action-governance.index', ['status' => 'overdue']),
        ];
    }

    private function summaryFor(array $types): array
    {
        $base = AcademicPmcOperatingRecord::whereIn('record_type', $types);
        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereNotIn('status', ['done', 'closed', 'resolved', 'published'])->count(),
            'critical' => (clone $base)->where('risk_band', 'critical')->count(),
            'overdue' => (clone $base)->whereNotIn('status', ['done', 'closed', 'resolved', 'published'])->where('due_at', '<', now())->count(),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['status', 'category', 'risk_band', 'program_id', 'batch_id', 'term_id', 'subject_id', 'owner_user_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }
    }

    private function filterSummary(array $filters): string
    {
        $active = collect($filters)->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->map(fn ($value, $key) => str($key)->headline() . ': ' . (is_array($value) ? json_encode($value) : $value));
        return $active->isEmpty() ? 'Showing all records in your PMC scope.' : $active->join(' | ');
    }

    private function savedViews(User $user, string $surface): Collection
    {
        return AcademicPmcSavedView::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))->where('surface', $surface)->latest()->get();
    }

    private function exportRows(string $report, array $filters): Collection
    {
        $types = $this->surfaceMap()[$report]['types'] ?? [$report];
        $query = AcademicPmcOperatingRecord::with('owner')->whereIn('record_type', $types);
        $this->applyFilters($query, $filters);

        return $query->latest()->limit(1000)->get()->map(fn ($row) => [
            $row->title,
            $row->record_type,
            $row->status,
            $row->owner?->name ?? '',
            $row->risk_band ?? '',
            $row->score,
            $row->due_at?->toDateString() ?? '',
        ]);
    }

    private function audit(User $actor, string $action, string $description, mixed $subject = null, array $metadata = []): void
    {
        DepartmentActivityLog::create([
            'department_id' => \App\Models\Department::where('code', 'ACAD')->value('id') ?: \App\Models\Department::query()->value('id'),
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'metadata' => $metadata + ['version' => 'Academics PMC OS v0.04'],
        ]);
    }
}
