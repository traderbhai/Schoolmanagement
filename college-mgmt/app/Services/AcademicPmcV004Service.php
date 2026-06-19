<?php

namespace App\Services;

use App\Models\AcademicPmcAnalyticsSnapshot;
use App\Models\AcademicPmcActionDependency;
use App\Models\AcademicPmcActionEvidence;
use App\Models\AcademicPmcActionReminder;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcAutomationExecution;
use App\Models\AcademicPmcAutomationRule;
use App\Models\AcademicPmcCourseDeliveryCheckpoint;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupDeliveryTracker;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcPolicyAudit;
use App\Models\AcademicPmcParentEscalation;
use App\Models\AcademicPmcPlanningCycle;
use App\Models\AcademicPmcReadinessItem;
use App\Models\AcademicPmcRemedialAction;
use App\Models\AcademicPmcReviewGovernanceRecord;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcSessionDeliveryLog;
use App\Models\AcademicPmcStudentIntervention;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\AcademicPmcWorkItem;
use App\Models\Attendance;
use App\Models\CourseFeedback;
use App\Models\DepartmentActivityLog;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\StudentGrievance;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\TimetableEntry;
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
            'planningCycles' => in_array($surface, ['planning', 'semester-readiness', 'academic-calendar'], true) ? $this->planningCycles($filters) : null,
            'readinessItems' => in_array($surface, ['planning', 'semester-readiness'], true) ? $this->readinessItems($filters) : null,
            'readinessBlockers' => in_array($surface, ['planning', 'semester-readiness'], true) ? $this->readinessBlockers($filters) : null,
            'studentPlans' => in_array($surface, ['student-success-v004', 'interventions', 'mentor-governance', 'parent-escalations'], true) ? $this->studentSuccessPlans($filters) : null,
            'studentInterventions' => in_array($surface, ['student-success-v004', 'interventions', 'mentor-governance'], true) ? $this->studentInterventions($filters) : null,
            'parentEscalations' => in_array($surface, ['student-success-v004', 'parent-escalations'], true) ? $this->parentEscalations($filters) : null,
            'studentSuccessSummary' => in_array($surface, ['student-success-v004', 'interventions', 'mentor-governance', 'parent-escalations'], true) ? $this->studentSuccessSummary() : null,
            'studentSuccessEffectivenessDiagnostics' => in_array($surface, ['student-success-v004', 'interventions', 'mentor-governance', 'parent-escalations'], true) ? $this->studentSuccessEffectivenessDiagnostics() : null,
            'deliveryCheckpoints' => in_array($surface, ['course-delivery', 'delivery-risk', 'remedial-planning'], true) ? $this->deliveryCheckpoints($filters) : null,
            'groupDeliveryTrackers' => in_array($surface, ['course-delivery', 'delivery-risk', 'remedial-planning'], true) ? $this->groupDeliveryTrackers($filters) : null,
            'sessionDeliveryLogs' => in_array($surface, ['course-delivery', 'delivery-risk', 'remedial-planning'], true) ? $this->sessionDeliveryLogs($filters) : null,
            'remedialActions' => in_array($surface, ['course-delivery', 'delivery-risk', 'remedial-planning'], true) ? $this->remedialActions($filters) : null,
            'deliverySummary' => in_array($surface, ['course-delivery', 'delivery-risk', 'remedial-planning'], true) ? $this->deliverySummary() : null,
            'deliveryExecutionDiagnostics' => in_array($surface, ['course-delivery', 'delivery-risk', 'remedial-planning'], true) ? $this->deliveryExecutionDiagnostics() : null,
            'summary' => $this->summaryFor($config['types']),
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters),
            'savedViews' => $this->savedViews($user, $surface),
            'legacyLinks' => $config['legacyLinks'] ?? [],
            'selectorOptions' => in_array($surface, ['planning', 'semester-readiness', 'academic-calendar', 'student-success-v004', 'interventions', 'mentor-governance', 'parent-escalations'], true) ? $this->selectorOptions() : [],
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
            'dependencies' => AcademicPmcActionDependency::with(['workItem', 'dependsOn'])->latest()->paginate(8, ['*'], 'dependencies_page'),
            'reminders' => AcademicPmcActionReminder::with(['workItem', 'owner', 'escalatedTo'])->orderBy('due_at')->paginate(8, ['*'], 'reminders_page'),
            'evidence' => AcademicPmcActionEvidence::with(['workItem', 'uploader', 'verifier'])->latest()->paginate(8, ['*'], 'evidence_page'),
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
            'enforcementDiagnostics' => $this->policyEnforcementDiagnostics(),
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

    public function approveMinutes(User $actor, AcademicPmcReviewGovernanceRecord $minutes, ?string $note = null): AcademicPmcReviewGovernanceRecord
    {
        abort_unless($minutes->record_type === 'minutes', 422, 'Only minutes records can be approved.');

        $minutes->update([
            'status' => 'approved',
            'closed_at' => now(),
            'evidence' => array_merge($minutes->evidence ?: [], [['label' => 'minutes approved', 'note' => $note, 'approved_by' => $actor->id, 'approved_at' => now()->toDateTimeString()]]),
            'metadata' => array_merge($minutes->metadata ?: [], ['approved_by' => $actor->id, 'approved_at' => now()->toDateTimeString(), 'version' => 'Academics PMC OS v0.060']),
        ]);

        $action = AcademicPmcWorkItem::updateOrCreate(
            ['source_type' => 'academic_pmc_review_minutes', 'source_key' => (string) $minutes->id],
            [
                'work_type' => 'review_action',
                'title' => 'Follow-up: ' . $minutes->title,
                'description' => 'Auto-created from approved PMC minutes. ' . ($minutes->body ?: ''),
                'owner_user_id' => $minutes->owner_user_id ?: $actor->id,
                'assigned_by' => $actor->id,
                'priority' => 'high',
                'severity' => 'high',
                'status' => 'open',
                'due_at' => now()->addDays(3),
                'metadata' => ['minutes_id' => $minutes->id, 'requires_evidence' => true, 'version' => 'Academics PMC OS v0.060'],
            ]
        );

        AcademicPmcActionReminder::firstOrCreate(
            ['work_item_id' => $action->id, 'reminder_type' => 'minutes_followup'],
            ['owner_user_id' => $action->owner_user_id, 'status' => 'scheduled', 'due_at' => $action->due_at?->copy()->subDay() ?: now()->addDays(2), 'message' => 'PMC minutes follow-up action is due soon.', 'metadata' => ['version' => 'Academics PMC OS v0.060']]
        );

        $this->audit($actor, 'academic_pmc_v060_minutes_approved', 'PMC minutes approved and follow-up action created', $minutes, ['work_item_id' => $action->id]);
        return $minutes->fresh();
    }

    public function addActionDependency(User $actor, AcademicPmcWorkItem $item, AcademicPmcWorkItem $dependsOn, array $data): AcademicPmcActionDependency
    {
        abort_if($item->id === $dependsOn->id, 422, 'An action cannot depend on itself.');

        $dependency = AcademicPmcActionDependency::updateOrCreate(
            ['work_item_id' => $item->id, 'depends_on_work_item_id' => $dependsOn->id],
            [
                'dependency_type' => $data['dependency_type'] ?? 'blocked_by',
                'status' => in_array($dependsOn->status, ['done', 'closed', 'verified'], true) ? 'resolved' : 'active',
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor->id,
                'resolved_at' => in_array($dependsOn->status, ['done', 'closed', 'verified'], true) ? now() : null,
                'metadata' => ['version' => 'Academics PMC OS v0.060'],
            ]
        );

        if ($dependency->status === 'active') {
            $item->update(['status' => 'blocked', 'metadata' => array_merge($item->metadata ?: [], ['blocked_by' => $dependsOn->id])]);
        }

        $this->audit($actor, 'academic_pmc_v060_action_dependency_added', 'PMC action dependency added', $dependency);
        return $dependency->fresh();
    }

    public function addActionEvidence(User $actor, AcademicPmcWorkItem $item, array $data): AcademicPmcActionEvidence
    {
        $evidence = AcademicPmcActionEvidence::create([
            'work_item_id' => $item->id,
            'uploaded_by' => $actor->id,
            'title' => $data['title'],
            'evidence_type' => $data['evidence_type'] ?? 'note',
            'evidence_note' => $data['evidence_note'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'verification_status' => 'submitted',
            'metadata' => ['version' => 'Academics PMC OS v0.060'],
        ]);

        $item->update(['metadata' => array_merge($item->metadata ?: [], ['latest_evidence_id' => $evidence->id])]);
        $this->audit($actor, 'academic_pmc_v060_action_evidence_added', 'PMC action evidence added', $evidence);
        return $evidence;
    }

    public function verifyActionClosure(User $actor, AcademicPmcWorkItem $item, array $data): AcademicPmcWorkItem
    {
        $activeDependency = AcademicPmcActionDependency::where('work_item_id', $item->id)->where('status', 'active')->exists();
        abort_if($activeDependency, 422, 'Action has active dependencies.');

        $verifiedEvidence = AcademicPmcActionEvidence::where('work_item_id', $item->id)->where('verification_status', 'verified')->exists();
        if (! $verifiedEvidence) {
            $evidence = AcademicPmcActionEvidence::where('work_item_id', $item->id)->latest()->first();
            abort_unless($evidence, 422, 'Evidence is required before closure verification.');
            $evidence->update([
                'verification_status' => 'verified',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_note' => $data['verification_note'] ?? 'Verified during PMC action closure.',
            ]);
        }

        $item->update([
            'status' => $data['status'] ?? 'verified',
            'metadata' => array_merge($item->metadata ?: [], ['closure_verified_by' => $actor->id, 'closure_verified_at' => now()->toDateTimeString(), 'closure_note' => $data['verification_note'] ?? null]),
        ]);

        AcademicPmcActionReminder::where('work_item_id', $item->id)->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'completed', 'sent_at' => now()]);
        $this->audit($actor, 'academic_pmc_v060_action_closure_verified', 'PMC action closure verified', $item);
        return $item->fresh();
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

    public function createPlanningCycle(User $actor, array $data): AcademicPmcPlanningCycle
    {
        $cycle = AcademicPmcPlanningCycle::create($data + [
            'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
            'status' => $data['status'] ?? 'draft',
            'readiness_score' => 0,
            'metadata' => ['version' => 'Academics PMC OS v0.056'],
        ]);

        foreach ($this->defaultReadinessSections($cycle->cycle_type) as $index => [$section, $title, $description, $severity]) {
            AcademicPmcReadinessItem::firstOrCreate(
                ['planning_cycle_id' => $cycle->id, 'section' => $section],
                [
                    'title' => $title,
                    'description' => $description,
                    'owner_user_id' => $cycle->owner_user_id,
                    'status' => $index < 2 ? 'in_progress' : 'open',
                    'severity' => $severity,
                    'completion_percent' => $index < 2 ? 65 : 20,
                    'is_blocker' => in_array($severity, ['high', 'critical'], true),
                    'due_at' => now()->addDays($index + 2),
                    'source_type' => 'pmc_planning_cycle',
                    'source_key' => (string) $cycle->id,
                    'metadata' => ['cycle_type' => $cycle->cycle_type],
                ]
            );
        }

        $this->refreshPlanningReadinessScore($cycle);
        $this->audit($actor, 'academic_pmc_v056_planning_cycle_created', $cycle->title, $cycle);
        return $cycle->fresh('readinessItems');
    }

    public function updatePlanningCycleStatus(User $actor, AcademicPmcPlanningCycle $cycle, string $status, ?string $note): AcademicPmcPlanningCycle
    {
        if (in_array($status, ['rejected', 'returned', 'revision_requested'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $cycle->update([
            'status' => $status,
            'approved_by' => in_array($status, ['approved', 'published'], true) ? $actor->id : $cycle->approved_by,
            'approved_at' => in_array($status, ['approved', 'published'], true) ? now() : $cycle->approved_at,
            'metadata' => array_merge($cycle->metadata ?: [], ['last_decision_note' => $note, 'last_decision_by' => $actor->id]),
        ]);

        $this->audit($actor, 'academic_pmc_v056_planning_cycle_status_updated', $cycle->title . ' moved to ' . $status, $cycle);
        return $cycle->fresh();
    }

    public function updateReadinessItem(User $actor, AcademicPmcReadinessItem $item, array $data): AcademicPmcReadinessItem
    {
        $item->update($data + [
            'completed_at' => ($data['status'] ?? null) === 'done' ? now() : $item->completed_at,
        ]);
        $this->refreshPlanningReadinessScore($item->planningCycle);
        $this->audit($actor, 'academic_pmc_v056_readiness_item_updated', $item->title, $item);
        return $item->fresh();
    }

    public function createWorkItemFromReadiness(User $actor, AcademicPmcReadinessItem $item): AcademicPmcWorkItem
    {
        $cycle = $item->planningCycle;
        $work = AcademicPmcWorkItem::updateOrCreate(
            ['source_type' => 'academic_pmc_readiness_item', 'source_key' => (string) $item->id],
            [
                'work_type' => 'semester_readiness',
                'title' => $item->title,
                'description' => $item->description ?: 'Readiness blocker converted into a PMC action item.',
                'program_id' => $cycle?->program_id,
                'batch_id' => $cycle?->batch_id,
                'term_id' => $cycle?->term_id,
                'owner_user_id' => $item->owner_user_id ?: $actor->id,
                'assigned_by' => $actor->id,
                'priority' => in_array($item->severity, ['high', 'critical'], true) ? 'high' : 'normal',
                'severity' => $item->severity,
                'status' => 'open',
                'due_at' => $item->due_at,
                'metadata' => ['planning_cycle_id' => $item->planning_cycle_id, 'readiness_section' => $item->section, 'version' => 'Academics PMC OS v0.056'],
            ]
        );

        $this->audit($actor, 'academic_pmc_v056_readiness_work_item_created', $work->title, $work);
        return $work;
    }

    public function refreshStudentSuccessSignals(User $actor): array
    {
        $students = Student::with(['user', 'program', 'batch', 'mentor'])->where('status', 'active')->limit(500)->get();
        $refreshed = 0;
        $critical = 0;

        foreach ($students as $student) {
            $attendance = $student->calculateAttendancePercentage();
            $publishedResults = $this->publishedStudentResults($student);
            $marks = (float) ((clone $publishedResults)->avg('marks_obtained') ?: 0);
            $absences = (clone $publishedResults)->where('is_absent', true)->count();
            $openGrievances = StudentGrievance::where('student_id', $student->id)->whereIn('status', ['open', 'under_review', 'escalated'])->count();
            $mentorMeetings = $student->mentorMeetings()->where('meeting_date', '>=', now()->subDays(30))->count();

            $score = 0;
            $reasons = [];
            if ($attendance > 0 && $attendance < 75) {
                $score += 30;
                $reasons[] = 'Attendance below 75%';
            }
            if ($marks > 0 && $marks < 45) {
                $score += 30;
                $reasons[] = 'Average marks below 45';
            }
            if ($absences > 0) {
                $score += min(20, $absences * 8);
                $reasons[] = 'Exam/internal assessment absence';
            }
            if ($openGrievances > 0) {
                $score += min(20, $openGrievances * 10);
                $reasons[] = 'Open grievance';
            }
            if ($mentorMeetings === 0) {
                $score += 15;
                $reasons[] = 'No mentor meeting in last 30 days';
            }

            $score = min(100, $score);
            $band = $score >= 70 ? 'critical' : ($score >= 45 ? 'high' : ($score >= 25 ? 'medium' : 'low'));
            if ($band === 'critical') {
                $critical++;
            }

            AcademicPmcStudentSuccessPlan::updateOrCreate(
                ['student_id' => $student->id, 'risk_type' => 'retention_risk'],
                [
                    'program_id' => $student->program_id,
                    'batch_id' => $student->batch_id,
                    'mentor_user_id' => $student->mentor_id ?: $actor->id,
                    'risk_band' => $band,
                    'status' => in_array($band, ['critical', 'high'], true) ? 'intervention_due' : 'monitoring',
                    'intervention_plan' => $this->recommendedStudentIntervention($band, $reasons),
                    'next_review_at' => now()->addDays(in_array($band, ['critical', 'high'], true) ? 3 : 14),
                    'parent_escalation_required' => $band === 'critical' || in_array('Attendance below 75%', $reasons, true),
                    'signals' => [
                        'risk_score' => $score,
                        'attendance_percent' => $attendance,
                        'average_marks' => round($marks, 2),
                        'exam_absences' => $absences,
                        'open_grievances' => $openGrievances,
                        'mentor_meetings_30d' => $mentorMeetings,
                        'reasons' => $reasons,
                    ],
                    'metadata' => ['version' => 'Academics PMC OS v0.057', 'refreshed_by' => $actor->id],
                ]
            );
            $refreshed++;
        }

        $this->audit($actor, 'academic_pmc_v057_student_success_refreshed', 'PMC student success signals refreshed', null, ['students' => $refreshed, 'critical' => $critical]);
        return ['students' => $refreshed, 'critical' => $critical];
    }

    private function publishedStudentResults(Student $student)
    {
        return $student->examResults()
            ->whereHas('exam', fn ($query) => $query->whereNotNull('published_at'));
    }

    public function createStudentIntervention(User $actor, AcademicPmcStudentSuccessPlan $plan, array $data): AcademicPmcStudentIntervention
    {
        $intervention = AcademicPmcStudentIntervention::create([
            'student_success_plan_id' => $plan->id,
            'student_id' => $plan->student_id,
            'program_id' => $plan->program_id,
            'batch_id' => $plan->batch_id,
            'owner_user_id' => $data['owner_user_id'] ?? $plan->mentor_user_id ?? $actor->id,
            'created_by' => $actor->id,
            'intervention_type' => $data['intervention_type'],
            'status' => 'open',
            'priority' => $data['priority'] ?? (in_array($plan->risk_band, ['critical', 'high'], true) ? 'high' : 'normal'),
            'reason' => $data['reason'] ?? 'Created from PMC student success risk.',
            'action_plan' => $data['action_plan'] ?? $plan->intervention_plan,
            'due_at' => $data['due_at'] ?? now()->addDays(3),
            'metadata' => ['risk_band' => $plan->risk_band, 'signals' => $plan->signals, 'version' => 'Academics PMC OS v0.057'],
        ]);
        $plan->update(['status' => 'intervention_assigned']);
        $this->audit($actor, 'academic_pmc_v057_intervention_created', 'Student intervention created', $intervention);
        return $intervention;
    }

    public function updateStudentIntervention(User $actor, AcademicPmcStudentIntervention $intervention, array $data): AcademicPmcStudentIntervention
    {
        $intervention->update($data + [
            'completed_at' => in_array($data['status'] ?? '', ['resolved', 'closed'], true) ? now() : $intervention->completed_at,
        ]);
        $this->audit($actor, 'academic_pmc_v057_intervention_updated', 'Student intervention updated', $intervention);
        return $intervention->fresh();
    }

    public function createParentEscalation(User $actor, AcademicPmcStudentSuccessPlan $plan, array $data): AcademicPmcParentEscalation
    {
        $student = $plan->student;
        $escalation = AcademicPmcParentEscalation::create([
            'student_success_plan_id' => $plan->id,
            'intervention_id' => $data['intervention_id'] ?? null,
            'student_id' => $plan->student_id,
            'owner_user_id' => $data['owner_user_id'] ?? $plan->mentor_user_id ?? $actor->id,
            'created_by' => $actor->id,
            'guardian_name' => $data['guardian_name'] ?? $student?->guardian_name,
            'guardian_phone' => $data['guardian_phone'] ?? $student?->guardian_phone,
            'reason' => $data['reason'] ?? 'retention_risk',
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'] ?? now()->addDay(),
            'metadata' => ['risk_band' => $plan->risk_band, 'version' => 'Academics PMC OS v0.057'],
        ]);
        $plan->update(['parent_escalation_required' => true]);
        $this->audit($actor, 'academic_pmc_v057_parent_escalation_created', 'Parent escalation created', $escalation);
        return $escalation;
    }

    public function refreshCourseDeliveryCheckpoints(User $actor): array
    {
        $subjects = Subject::with('program')->where('is_active', true)->limit(500)->get();
        $refreshed = 0;
        $critical = 0;

        foreach ($subjects as $subject) {
            $entries = TimetableEntry::where('subject_id', $subject->id);
            $planned = (clone $entries)->where('is_active', true)->count();
            $entryIds = (clone $entries)->pluck('id');
            $conducted = $entryIds->isNotEmpty()
                ? Attendance::whereIn('timetable_entry_id', $entryIds)->distinct('date')->count('date')
                : 0;
            $missed = max(0, $planned - $conducted);
            $attendanceTotal = $entryIds->isNotEmpty() ? Attendance::whereIn('timetable_entry_id', $entryIds)->count() : 0;
            $attendancePresent = $entryIds->isNotEmpty() ? Attendance::whereIn('timetable_entry_id', $entryIds)->where('status', 'present')->count() : 0;
            $attendancePercent = $attendanceTotal > 0 ? round(($attendancePresent / $attendanceTotal) * 100, 2) : 0;
            $marksPending = ExamResult::whereHas('exam', fn ($q) => $q->where('subject_id', $subject->id))->whereNull('marks_obtained')->count();
            $feedback = CourseFeedback::where('subject_id', $subject->id)->avg('overall_rating');
            $assignment = SubjectFacultyAssignment::where('subject_id', $subject->id)->where('is_primary', true)->first()
                ?: SubjectFacultyAssignment::where('subject_id', $subject->id)->first();

            $risk = 0;
            $reasons = [];
            if ($planned === 0) {
                $risk += 30;
                $reasons[] = 'No planned timetable sessions';
            }
            if ($missed > 0) {
                $risk += min(30, $missed * 10);
                $reasons[] = 'Planned sessions not conducted';
            }
            if ($attendancePercent > 0 && $attendancePercent < 75) {
                $risk += 25;
                $reasons[] = 'Low attendance in delivered sessions';
            }
            if ($marksPending > 0) {
                $risk += min(25, $marksPending * 5);
                $reasons[] = 'Marks pending';
            }
            if ($feedback !== null && $feedback < 3.5) {
                $risk += 20;
                $reasons[] = 'Low course feedback';
            }

            $risk = min(100, $risk);
            $band = $risk >= 70 ? 'critical' : ($risk >= 45 ? 'high' : ($risk >= 25 ? 'medium' : 'low'));
            if ($band === 'critical') {
                $critical++;
            }
            $score = max(0, 100 - $risk);

            AcademicPmcCourseDeliveryCheckpoint::updateOrCreate(
                ['subject_id' => $subject->id, 'term_id' => $assignment?->term_id],
                [
                    'program_id' => $subject->program_id,
                    'batch_id' => $assignment?->batch_id,
                    'teacher_id' => $assignment?->teacher_id,
                    'owner_user_id' => $actor->id,
                    'planned_sessions' => $planned,
                    'conducted_sessions' => $conducted,
                    'missed_sessions' => $missed,
                    'marks_pending_count' => $marksPending,
                    'attendance_percent' => $attendancePercent,
                    'feedback_score' => $feedback ? round((float) $feedback, 2) : null,
                    'delivery_score' => $score,
                    'risk_band' => $band,
                    'status' => in_array($band, ['critical', 'high'], true) ? 'action_required' : 'monitoring',
                    'next_review_at' => now()->addDays(in_array($band, ['critical', 'high'], true) ? 3 : 14),
                    'signals' => ['risk_score' => $risk, 'reasons' => $reasons],
                    'metadata' => ['version' => 'Academics PMC OS v0.058', 'refreshed_by' => $actor->id],
                ]
            );
            $refreshed++;
        }

        $groupResult = $this->refreshGroupDeliveryTrackers($actor);

        $this->audit($actor, 'academic_pmc_v058_delivery_checkpoints_refreshed', 'PMC course delivery checkpoints refreshed', null, ['subjects' => $refreshed, 'critical' => $critical, 'groups' => $groupResult['groups']]);
        return ['subjects' => $refreshed, 'critical' => $critical, 'groups' => $groupResult['groups'], 'group_critical' => $groupResult['critical']];
    }

    public function refreshGroupDeliveryTrackers(User $actor): array
    {
        $groups = AcademicPmcCourseGroup::with(['subject', 'program', 'members'])
            ->whereIn('status', ['active', 'locked', 'published', 'ready'])
            ->orWhere('is_locked', true)
            ->limit(500)
            ->get();
        $refreshed = 0;
        $critical = 0;

        foreach ($groups as $group) {
            $primary = AcademicPmcGroupFacultyAssignment::where('course_group_id', $group->id)
                ->where('assignment_role', 'primary')
                ->first()
                ?: AcademicPmcGroupFacultyAssignment::where('course_group_id', $group->id)->first();

            $items = AcademicPmcTimetableGenerationItem::with(['slot', 'classroom'])
                ->where('course_group_id', $group->id)
                ->whereIn('status', ['scheduled', 'published', 'locked'])
                ->get();

            $planned = max($items->count(), (int) ($group->constraints['planned_sessions'] ?? 0));
            $conducted = AcademicPmcSessionDeliveryLog::where('course_group_id', $group->id)->where('session_status', 'conducted')->count();
            $rescheduled = AcademicPmcSessionDeliveryLog::where('course_group_id', $group->id)->where('session_status', 'rescheduled')->count();
            $cancelled = AcademicPmcSessionDeliveryLog::where('course_group_id', $group->id)->where('session_status', 'cancelled')->count();
            $logged = AcademicPmcSessionDeliveryLog::where('course_group_id', $group->id)->count();
            $missed = max(0, $planned - $conducted - $rescheduled);
            $pendingLogs = max(0, $planned - $logged);

            $attendancePercent = null;
            $studentIds = $group->members()->where('status', 'active')->pluck('student_id');
            if ($studentIds->isNotEmpty()) {
                $attendanceQuery = Attendance::whereIn('student_id', $studentIds);
                $total = (clone $attendanceQuery)->count();
                $present = (clone $attendanceQuery)->whereIn('status', ['present', 'late'])->count();
                $attendancePercent = $total > 0 ? round(($present / $total) * 100, 2) : null;
            }

            $risk = 0;
            $reasons = [];
            $actions = [];
            if ($planned === 0) {
                $risk += 35;
                $reasons[] = 'No planned sessions for group';
                $actions[] = 'create timetable sessions';
            }
            if ($pendingLogs > 0) {
                $risk += min(25, $pendingLogs * 5);
                $reasons[] = 'Session delivery logs pending';
                $actions[] = 'collect faculty session logs';
            }
            if ($missed > 0) {
                $risk += min(35, $missed * 12);
                $reasons[] = 'Group is behind planned delivery';
                $actions[] = 'schedule group makeup class';
            }
            if ($attendancePercent !== null && $attendancePercent < 75) {
                $risk += 20;
                $reasons[] = 'Group attendance below threshold';
                $actions[] = 'start attendance intervention';
            }
            if (! $primary) {
                $risk += 25;
                $reasons[] = 'No primary faculty assigned to group';
                $actions[] = 'assign primary faculty';
            }

            $risk = min(100, $risk);
            $band = $risk >= 70 ? 'critical' : ($risk >= 45 ? 'high' : ($risk >= 25 ? 'medium' : 'low'));
            if ($band === 'critical') {
                $critical++;
            }

            $tracker = AcademicPmcGroupDeliveryTracker::updateOrCreate(
                ['course_group_id' => $group->id],
                [
                    'program_id' => $group->program_id,
                    'batch_id' => $group->batch_id,
                    'term_id' => $group->term_id,
                    'subject_id' => $group->subject_id,
                    'teacher_id' => $primary?->teacher_id,
                    'owner_user_id' => $group->owner_user_id ?: $actor->id,
                    'planned_sessions' => $planned,
                    'conducted_sessions' => $conducted,
                    'missed_sessions' => $missed,
                    'rescheduled_sessions' => $rescheduled,
                    'cancelled_sessions' => $cancelled,
                    'pending_session_logs' => $pendingLogs,
                    'attendance_percent' => $attendancePercent,
                    'delivery_progress' => $planned > 0 ? min(100, (int) round(($conducted / $planned) * 100)) : 0,
                    'risk_score' => $risk,
                    'risk_band' => $band,
                    'status' => in_array($band, ['critical', 'high'], true) ? 'action_required' : 'monitoring',
                    'next_review_at' => now()->addDays(in_array($band, ['critical', 'high'], true) ? 2 : 10),
                    'risk_reasons' => $reasons,
                    'recommended_actions' => array_values(array_unique($actions ?: ['review group delivery'])),
                    'metadata' => ['version' => 'Academics PMC OS v0.059', 'refreshed_by' => $actor->id],
                ]
            );

            foreach ($items as $index => $item) {
                $scheduledDate = now()->startOfWeek()->addDays(max(0, ($item->day_of_week ?? 1) - 1))->toDateTimeString();
                AcademicPmcSessionDeliveryLog::firstOrCreate(
                    ['generation_item_id' => $item->id, 'scheduled_date' => $scheduledDate],
                    [
                        'group_delivery_tracker_id' => $tracker->id,
                        'course_group_id' => $group->id,
                        'subject_id' => $group->subject_id,
                        'teacher_id' => $item->teacher_id ?: $primary?->teacher_id,
                        'classroom_id' => $item->classroom_id,
                        'timetable_slot_id' => $item->timetable_slot_id,
                        'day_of_week' => $item->day_of_week,
                        'session_status' => $index === 0 && in_array($band, ['critical', 'high'], true) ? 'missed' : 'planned',
                        'delivery_type' => $group->group_type === 'lab_group' ? 'lab' : 'lecture',
                        'attendance_marked' => false,
                        'lesson_plan_updated' => false,
                        'material_uploaded' => false,
                        'topic_planned' => $group->subject?->name ? 'Planned topic for ' . $group->subject->name : null,
                        'gap_reason' => $index === 0 && in_array($band, ['critical', 'high'], true) ? 'Pending faculty delivery log' : null,
                        'metadata' => ['version' => 'Academics PMC OS v0.059'],
                    ]
                );
            }

            $refreshed++;
        }

        $this->audit($actor, 'academic_pmc_v059_group_delivery_refreshed', 'PMC group delivery trackers refreshed', null, ['groups' => $refreshed, 'critical' => $critical]);
        return ['groups' => $refreshed, 'critical' => $critical];
    }

    public function createRemedialAction(User $actor, AcademicPmcCourseDeliveryCheckpoint $checkpoint, array $data): AcademicPmcRemedialAction
    {
        $action = AcademicPmcRemedialAction::create([
            'checkpoint_id' => $checkpoint->id,
            'subject_id' => $checkpoint->subject_id,
            'teacher_id' => $checkpoint->teacher_id,
            'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
            'created_by' => $actor->id,
            'action_type' => $data['action_type'],
            'status' => 'open',
            'priority' => $data['priority'] ?? (in_array($checkpoint->risk_band, ['critical', 'high'], true) ? 'high' : 'normal'),
            'reason' => $data['reason'] ?? 'Created from PMC delivery checkpoint.',
            'action_plan' => $data['action_plan'] ?? $this->recommendedDeliveryAction($checkpoint),
            'due_at' => $data['due_at'] ?? now()->addDays(3),
            'metadata' => ['risk_band' => $checkpoint->risk_band, 'signals' => $checkpoint->signals, 'version' => 'Academics PMC OS v0.058'],
        ]);
        $checkpoint->update(['status' => 'remedial_assigned']);
        $this->audit($actor, 'academic_pmc_v058_remedial_action_created', 'Course delivery remedial action created', $action);
        return $action;
    }

    public function updateRemedialAction(User $actor, AcademicPmcRemedialAction $action, array $data): AcademicPmcRemedialAction
    {
        $action->update($data + [
            'completed_at' => in_array($data['status'] ?? '', ['resolved', 'closed'], true) ? now() : $action->completed_at,
        ]);
        $this->audit($actor, 'academic_pmc_v058_remedial_action_updated', 'Course delivery remedial action updated', $action);
        return $action->fresh();
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

    private function policyEnforcementDiagnostics(): array
    {
        $contracts = collect([
            ['route' => 'academics.pmc.action-governance.dependencies.store', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'work_item'],
            ['route' => 'academics.pmc.action-governance.evidence.store', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'work_item'],
            ['route' => 'academics.pmc.action-governance.actions.verify', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'work_item'],
            ['route' => 'academics.pmc.course-allocation-exceptions.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'subject_or_batch'],
            ['route' => 'academics.pmc.course-allocation-exceptions.decide', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'exception'],
            ['route' => 'academics.pmc.course-allocation.bulk-core', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'program_batch_term'],
            ['route' => 'academics.pmc.course-delivery.refresh', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'department_signal'],
            ['route' => 'academics.pmc.course-delivery.checkpoints.remedial-actions.store', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'checkpoint'],
            ['route' => 'academics.pmc.course-group-adjustments.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'group'],
            ['route' => 'academics.pmc.course-group-adjustments.decide', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'group_adjustment'],
            ['route' => 'academics.pmc.course-groups.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'program_batch_term'],
            ['route' => 'academics.pmc.course-groups.auto-build', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'program_batch_term'],
            ['route' => 'academics.pmc.curriculum-validations.refresh', 'risk' => 'medium', 'style' => 'broad_write', 'scope' => 'department_signal'],
            ['route' => 'academics.pmc.data-reconciliation.refresh', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'department_signal'],
            ['route' => 'academics.pmc.data-reconciliation.runs.mark-failed', 'risk' => 'medium', 'style' => 'conditional_scope', 'scope' => 'reconciliation_run'],
            ['route' => 'academics.pmc.data-reconciliation.repair', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'reconciliation_check'],
            ['route' => 'academics.pmc.elective-allocation.process', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'program_batch_term'],
            ['route' => 'academics.pmc.faculty-availability-requests.store', 'risk' => 'medium', 'style' => 'scope_aware', 'scope' => 'teacher_term'],
            ['route' => 'academics.pmc.faculty-availability-requests.decide', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'availability_request'],
            ['route' => 'academics.pmc.faculty-load-reviews.refresh', 'risk' => 'medium', 'style' => 'broad_write', 'scope' => 'department_signal'],
            ['route' => 'academics.pmc.faculty-load-reviews.decide', 'risk' => 'medium', 'style' => 'scope_aware', 'scope' => 'load_review'],
            ['route' => 'academics.pmc.room-readiness-reviews.refresh', 'risk' => 'medium', 'style' => 'broad_write', 'scope' => 'department_signal'],
            ['route' => 'academics.pmc.room-readiness-reviews.decide', 'risk' => 'medium', 'style' => 'scope_aware', 'scope' => 'room_review'],
            ['route' => 'academics.pmc.section-faculty-allocation.assign', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'course_group'],
            ['route' => 'academics.pmc.faculty-assignment-acknowledgements.request', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'group_assignment'],
            ['route' => 'academics.pmc.faculty-assignment-acknowledgements.respond', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'assignment_ack'],
            ['route' => 'academics.pmc.faculty-assignment-acknowledgements.review', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'assignment_ack'],
            ['route' => 'academics.pmc.sections.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'program_batch_term'],
            ['route' => 'academics.pmc.timetable-generator.generate', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'generation_run'],
            ['route' => 'academics.pmc.timetable-generator.validate', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'generation_run'],
            ['route' => 'academics.pmc.timetable-generator.publish', 'risk' => 'critical', 'style' => 'scope_aware', 'scope' => 'generation_run'],
            ['route' => 'academics.pmc.timetable-generator.impact-preview', 'risk' => 'medium', 'style' => 'scope_aware', 'scope' => 'generation_run'],
            ['route' => 'academics.pmc.timetable-generator-items.apply-alternative', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'generation_item'],
            ['route' => 'academics.pmc.timetable-generator-items.move', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'generation_item'],
            ['route' => 'academics.pmc.timetable-change-requests.store', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'timetable_version_when_present'],
            ['route' => 'academics.pmc.timetable-change-requests.decide', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'timetable_change_request'],
            ['route' => 'academics.pmc.timetable-constraints.resolution-actions.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'generation_run'],
            ['route' => 'academics.pmc.timetable-resolution-actions.close', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'constraint_resolution'],
            ['route' => 'academics.pmc.timetable-versions-v041.freeze', 'risk' => 'critical', 'style' => 'scope_aware', 'scope' => 'timetable_version'],
            ['route' => 'academics.pmc.timetable-versions-v041.unfreeze', 'risk' => 'critical', 'style' => 'scope_aware', 'scope' => 'timetable_version'],
            ['route' => 'academics.pmc.timetable-versions-v041.rollback', 'risk' => 'critical', 'style' => 'scope_aware', 'scope' => 'timetable_version'],
            ['route' => 'academics.pmc.locked-slots.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'program_batch_term'],
            ['route' => 'academics.pmc.substitution-intelligence.recommend', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'course_group_when_present'],
            ['route' => 'academics.pmc.timetable-notifications.store', 'risk' => 'medium', 'style' => 'broad_write', 'scope' => 'source_metadata_review_needed'],
            ['route' => 'academics.pmc.timetable-notifications.update-status', 'risk' => 'medium', 'style' => 'broad_write', 'scope' => 'notification'],
            ['route' => 'academics.pmc.timetable-notifications.retry', 'risk' => 'medium', 'style' => 'broad_write', 'scope' => 'notification'],
            ['route' => 'academics.pmc.student-course-basket-acknowledgements.review', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'student_acknowledgement'],
            ['route' => 'academics.pmc.interventions.update', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'intervention'],
            ['route' => 'academics.pmc.student-success-v004.interventions.store', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'student_plan'],
            ['route' => 'academics.pmc.student-success-v004.parent-escalations.store', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'student_plan'],
            ['route' => 'academics.pmc.student-success-v004.refresh', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'student_success_signal'],
            ['route' => 'academics.pmc.remedial-planning.actions.update', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'remedial_action'],
            ['route' => 'academics.pmc.planning.store', 'risk' => 'medium', 'style' => 'scope_aware', 'scope' => 'planning_cycle'],
            ['route' => 'academics.pmc.planning.cycles.update', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'planning_cycle'],
            ['route' => 'academics.pmc.semester-readiness.items.update', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'readiness_item'],
            ['route' => 'academics.pmc.semester-readiness.items.work-item', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'readiness_item'],
            ['route' => 'academics.pmc.v004.approvals.decide', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'approval'],
            ['route' => 'academics.pmc.v004.records.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'pmc_surface_record'],
            ['route' => 'academics.pmc.v004.records.update', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'pmc_record'],
            ['route' => 'academics.pmc.v004.records.work-item', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'pmc_record'],
            ['route' => 'academics.pmc.v004.automation.refresh', 'risk' => 'high', 'style' => 'broad_write', 'scope' => 'department_signal'],
            ['route' => 'academics.pmc.reviews.store', 'risk' => 'medium', 'style' => 'scope_aware', 'scope' => 'review_record'],
            ['route' => 'academics.pmc.saved-views.store', 'risk' => 'low', 'style' => 'scope_aware', 'scope' => 'pmc_scope'],
            ['route' => 'academics.pmc.work-items.store', 'risk' => 'high', 'style' => 'scope_aware', 'scope' => 'pmc_scope'],
            ['route' => 'academics.pmc.work-items.update', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'work_item'],
            ['route' => 'academics.pmc.meeting-minutes.approve', 'risk' => 'high', 'style' => 'conditional_scope', 'scope' => 'minutes'],
        ]);
        $auditRows = AcademicPmcPolicyAudit::whereIn('route_name', $contracts->pluck('route'))->get()->keyBy('route_name');
        $broadRoutes = $contracts->where('style', 'broad_write')->values();
        $scopeAwareRoutes = $contracts->whereIn('style', ['scope_aware', 'conditional_scope'])->values();
        $missingRows = $contracts->filter(fn (array $contract) => ! $auditRows->has($contract['route']))->values();
        $missingEnforcementRows = $auditRows->filter(fn (AcademicPmcPolicyAudit $audit) => $audit->missing_enforcement)->values();
        $untestedRows = $auditRows->filter(fn (AcademicPmcPolicyAudit $audit) => $audit->last_test_status !== 'passed')->values();

        return [
            'tracked_routes' => $contracts->count(),
            'critical_routes' => $contracts->where('risk', 'critical')->count(),
            'high_risk_routes' => $contracts->whereIn('risk', ['critical', 'high'])->count(),
            'scope_aware_routes' => $scopeAwareRoutes->count(),
            'broad_write_routes' => $broadRoutes->count(),
            'missing_audit_rows' => $missingRows->count(),
            'missing_enforcement_rows' => $missingEnforcementRows->count(),
            'untested_rows' => $untestedRows->count(),
            'status' => $broadRoutes->isEmpty() && $missingRows->isEmpty() && $missingEnforcementRows->isEmpty() && $untestedRows->isEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $broadRoutes->isEmpty()
                ? 'All tracked high-risk PMC write routes are scope-aware or conditionally scoped.'
                : 'Review broad write routes and convert source-backed timetable, automation, delivery, and student-success actions to record/scope-aware policy checks where feasible.',
            'broad_routes' => $broadRoutes,
            'scope_aware_route_names' => $scopeAwareRoutes->pluck('route')->values(),
            'missing_route_names' => $missingRows->pluck('route')->values(),
        ];
    }

    private function records(string $type, int $limit): Collection
    {
        return AcademicPmcOperatingRecord::with(['program', 'owner'])->where('record_type', $type)->latest()->limit($limit)->get();
    }

    private function readinessAverage(): int
    {
        $score = AcademicPmcPlanningCycle::avg('readiness_score');
        if ($score !== null) {
            return (int) round($score);
        }

        $avg = AcademicPmcOperatingRecord::where('record_type', 'semester_readiness')->avg('score');
        return (int) round($avg ?: 0);
    }

    private function planningCycles(array $filters): LengthAwarePaginator
    {
        $query = AcademicPmcPlanningCycle::with(['program', 'batch', 'term', 'owner'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($filters['term_id'] ?? null, fn ($q, $id) => $q->where('term_id', $id))
            ->latest();

        return $query->paginate(10, ['*'], 'cycles_page')->withQueryString();
    }

    private function readinessItems(array $filters): LengthAwarePaginator
    {
        $query = AcademicPmcReadinessItem::with(['planningCycle.program', 'owner'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['risk_band'] ?? null, fn ($q, $risk) => $q->where('severity', $risk))
            ->orderByRaw("case when severity = 'critical' then 0 when severity = 'high' then 1 when severity = 'medium' then 2 else 3 end")
            ->orderBy('due_at');

        return $query->paginate(15, ['*'], 'readiness_page')->withQueryString();
    }

    private function readinessBlockers(array $filters): int
    {
        return AcademicPmcReadinessItem::where('is_blocker', true)
            ->whereNotIn('status', ['done', 'closed', 'cancelled'])
            ->count();
    }

    private function studentSuccessPlans(array $filters): LengthAwarePaginator
    {
        return AcademicPmcStudentSuccessPlan::with(['student.user', 'program', 'mentor'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['risk_band'] ?? null, fn ($q, $risk) => $q->where('risk_band', $risk))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->orderByRaw("case when risk_band = 'critical' then 0 when risk_band = 'high' then 1 when risk_band = 'medium' then 2 else 3 end")
            ->orderBy('next_review_at')
            ->paginate(12, ['*'], 'student_plans_page')
            ->withQueryString();
    }

    private function studentInterventions(array $filters): LengthAwarePaginator
    {
        return AcademicPmcStudentIntervention::with(['student.user', 'program', 'owner', 'plan'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->orderByRaw("case when priority = 'critical' then 0 when priority = 'high' then 1 else 2 end")
            ->orderBy('due_at')
            ->paginate(12, ['*'], 'interventions_page')
            ->withQueryString();
    }

    private function parentEscalations(array $filters): LengthAwarePaginator
    {
        return AcademicPmcParentEscalation::with(['student.user', 'owner', 'plan'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('scheduled_at')
            ->paginate(12, ['*'], 'parent_escalations_page')
            ->withQueryString();
    }

    private function deliveryCheckpoints(array $filters): LengthAwarePaginator
    {
        return AcademicPmcCourseDeliveryCheckpoint::with(['subject.program', 'teacher.user', 'owner'])
            ->when($filters['risk_band'] ?? null, fn ($q, $risk) => $q->where('risk_band', $risk))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->orderByRaw("case when risk_band = 'critical' then 0 when risk_band = 'high' then 1 when risk_band = 'medium' then 2 else 3 end")
            ->orderBy('next_review_at')
            ->paginate(12, ['*'], 'delivery_page')
            ->withQueryString();
    }

    private function remedialActions(array $filters): LengthAwarePaginator
    {
        return AcademicPmcRemedialAction::with(['checkpoint.subject', 'subject', 'teacher.user', 'owner'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("case when priority = 'critical' then 0 when priority = 'high' then 1 else 2 end")
            ->orderBy('due_at')
            ->paginate(12, ['*'], 'remedial_page')
            ->withQueryString();
    }

    private function groupDeliveryTrackers(array $filters): LengthAwarePaginator
    {
        return AcademicPmcGroupDeliveryTracker::with(['courseGroup.subject', 'subject', 'teacher.user', 'owner'])
            ->when($filters['risk_band'] ?? null, fn ($q, $risk) => $q->where('risk_band', $risk))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->orderByRaw("case when risk_band = 'critical' then 0 when risk_band = 'high' then 1 when risk_band = 'medium' then 2 else 3 end")
            ->orderBy('next_review_at')
            ->paginate(12, ['*'], 'group_delivery_page')
            ->withQueryString();
    }

    private function sessionDeliveryLogs(array $filters): LengthAwarePaginator
    {
        return AcademicPmcSessionDeliveryLog::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('session_status', $status))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->whereHas('courseGroup', fn ($group) => $group->where('program_id', $id)))
            ->orderByRaw("case when session_status = 'missed' then 0 when session_status = 'planned' then 1 when session_status = 'conducted' then 2 else 3 end")
            ->orderBy('scheduled_date')
            ->paginate(12, ['*'], 'session_delivery_page')
            ->withQueryString();
    }

    private function deliverySummary(): array
    {
        return [
            'checkpoints' => AcademicPmcCourseDeliveryCheckpoint::count(),
            'group_trackers' => AcademicPmcGroupDeliveryTracker::count(),
            'critical' => AcademicPmcCourseDeliveryCheckpoint::where('risk_band', 'critical')->count(),
            'missed_sessions' => AcademicPmcCourseDeliveryCheckpoint::sum('missed_sessions'),
            'group_missed_sessions' => AcademicPmcGroupDeliveryTracker::sum('missed_sessions'),
            'pending_session_logs' => AcademicPmcGroupDeliveryTracker::sum('pending_session_logs'),
            'open_remedials' => AcademicPmcRemedialAction::whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
        ];
    }

    private function deliveryExecutionDiagnostics(): array
    {
        $logs = AcademicPmcSessionDeliveryLog::query();
        $trackers = AcademicPmcGroupDeliveryTracker::query();
        $checkpoints = AcademicPmcCourseDeliveryCheckpoint::query();

        $pendingFacultyLogs = (clone $trackers)->sum('pending_session_logs');
        $plannedLogs = (clone $logs)->where('session_status', 'planned')->count();
        $missedLogs = (clone $logs)->where('session_status', 'missed')->count();
        $cancelledLogs = (clone $logs)->where('session_status', 'cancelled')->count();
        $rescheduledLogs = (clone $logs)->where('session_status', 'rescheduled')->count();
        $attendancePending = (clone $logs)->where('session_status', 'conducted')->where('attendance_marked', false)->count();
        $lessonPlanPending = (clone $logs)->where('lesson_plan_updated', false)->count();
        $materialPending = (clone $logs)->where('material_uploaded', false)->count();
        $topicPlannedMissing = (clone $logs)->where(function ($query) {
            $query->whereNull('topic_planned')->orWhere('topic_planned', '');
        })->count();
        $topicCoveredMissing = (clone $logs)->where('session_status', 'conducted')->where(function ($query) {
            $query->whereNull('topic_covered')->orWhere('topic_covered', '');
        })->count();
        $behindGroups = (clone $trackers)->whereIn('risk_band', ['high', 'critical'])->count();
        $overdueReviews = (clone $checkpoints)->whereNotNull('next_review_at')->where('next_review_at', '<', now())->count();
        $openRemedials = AcademicPmcRemedialAction::whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count();

        $blockerTotal = $pendingFacultyLogs
            + $missedLogs
            + $cancelledLogs
            + $attendancePending
            + $lessonPlanPending
            + $topicCoveredMissing
            + $behindGroups
            + $overdueReviews
            + $openRemedials;

        return [
            'session_logs' => (clone $logs)->count(),
            'pending_faculty_logs' => $pendingFacultyLogs,
            'planned_logs' => $plannedLogs,
            'missed_logs' => $missedLogs,
            'cancelled_logs' => $cancelledLogs,
            'rescheduled_logs' => $rescheduledLogs,
            'attendance_pending' => $attendancePending,
            'lesson_plan_pending' => $lessonPlanPending,
            'material_pending' => $materialPending,
            'topic_planned_missing' => $topicPlannedMissing,
            'topic_covered_missing' => $topicCoveredMissing,
            'behind_groups' => $behindGroups,
            'overdue_reviews' => $overdueReviews,
            'open_remedials' => $openRemedials,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Course delivery execution logs are current.' : 'Collect pending faculty logs, complete topic/attendance/material updates, and close delivery remedial blockers.',
        ];
    }

    private function studentSuccessSummary(): array
    {
        return [
            'plans' => AcademicPmcStudentSuccessPlan::count(),
            'critical' => AcademicPmcStudentSuccessPlan::where('risk_band', 'critical')->count(),
            'interventions_open' => AcademicPmcStudentIntervention::whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
            'parent_due' => AcademicPmcParentEscalation::whereIn('status', ['scheduled', 'pending'])->count(),
        ];
    }

    private function studentSuccessEffectivenessDiagnostics(): array
    {
        $openStatuses = ['open', 'assigned', 'mentor_contacted', 'parent_contacted', 'remedial_assigned', 'under_review', 'escalated'];
        $closedStatuses = ['resolved', 'closed', 'cancelled'];
        $plans = AcademicPmcStudentSuccessPlan::query();
        $interventions = AcademicPmcStudentIntervention::query();
        $parentEscalations = AcademicPmcParentEscalation::query();

        $totalInterventions = (clone $interventions)->count();
        $resolvedInterventions = (clone $interventions)->whereIn('status', ['resolved', 'closed'])->count();
        $openInterventions = (clone $interventions)->whereIn('status', $openStatuses)->count();
        $overdueInterventions = (clone $interventions)
            ->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $evidenceGaps = (clone $interventions)
            ->whereIn('status', ['resolved', 'closed', 'under_review'])
            ->where(function ($query) {
                $query->whereNull('evidence')
                    ->orWhere('evidence', '[]')
                    ->orWhere('evidence', '{}');
            })
            ->count();
        $criticalPlans = (clone $plans)->whereIn('risk_band', ['critical', 'high'])->count();
        $stalePlanReviews = (clone $plans)
            ->whereNotIn('status', ['resolved', 'closed', 'cancelled'])
            ->where(function ($query) {
                $query->whereNull('next_review_at')
                    ->orWhere('next_review_at', '<', now()->subDays(7));
            })
            ->count();
        $parentDue = (clone $parentEscalations)->whereIn('status', ['scheduled', 'pending'])->count();
        $parentOverdue = (clone $parentEscalations)
            ->whereIn('status', ['scheduled', 'pending'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->count();
        $parentOutcomeMissing = (clone $parentEscalations)
            ->whereIn('status', ['completed', 'closed'])
            ->where(function ($query) {
                $query->whereNull('outcome_note')->orWhere('outcome_note', '');
            })
            ->count();
        $repeatRiskStudents = AcademicPmcStudentIntervention::query()
            ->select('student_id')
            ->whereNotNull('student_id')
            ->whereIn('status', $openStatuses)
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();
        $escalatedInterventions = (clone $interventions)->where('status', 'escalated')->count();
        $effectivenessRate = $totalInterventions > 0 ? (int) round(($resolvedInterventions / $totalInterventions) * 100) : 0;
        $blockerTotal = $overdueInterventions
            + $stalePlanReviews
            + $parentOverdue
            + $evidenceGaps
            + $parentOutcomeMissing
            + $repeatRiskStudents;

        return [
            'risk_plans' => (clone $plans)->count(),
            'critical_or_high_plans' => $criticalPlans,
            'open_interventions' => $openInterventions,
            'overdue_interventions' => $overdueInterventions,
            'resolved_interventions' => $resolvedInterventions,
            'effectiveness_rate' => $effectivenessRate,
            'stale_plan_reviews' => $stalePlanReviews,
            'parent_calls_due' => $parentDue,
            'parent_calls_overdue' => $parentOverdue,
            'parent_outcome_missing' => $parentOutcomeMissing,
            'evidence_gaps' => $evidenceGaps,
            'repeat_risk_students' => $repeatRiskStudents,
            'escalated_interventions' => $escalatedInterventions,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0
                ? 'Student success interventions are current and evidence-backed.'
                : 'Clear overdue interventions, stale reviews, parent-call delays, repeat-risk cases, and missing evidence before closing the student-success cycle.',
        ];
    }

    private function refreshPlanningReadinessScore(AcademicPmcPlanningCycle $cycle): void
    {
        $score = (int) round($cycle->readinessItems()->avg('completion_percent') ?: 0);
        $blockers = $cycle->readinessItems()->where('is_blocker', true)->whereNotIn('status', ['done', 'closed', 'cancelled'])->count();
        $cycle->update([
            'readiness_score' => $score,
            'metadata' => array_merge($cycle->metadata ?: [], ['open_blockers' => $blockers, 'score_refreshed_at' => now()->toDateTimeString()]),
        ]);
    }

    private function defaultReadinessSections(string $cycleType): array
    {
        return [
            ['curriculum_ready', 'Curriculum and syllabus approved', 'Syllabus version, CO/PO mapping, credit rules, and rollout checklist must be approved.', 'critical'],
            ['subjects_mapped', 'Subjects mapped to term and student baskets', 'Core, elective, backlog, repeat, and audit course baskets must be reviewed.', 'high'],
            ['faculty_assigned', 'Faculty and backup allocation ready', 'Primary, co-faculty, lab/tutorial, backup, and acknowledgement status must be clear.', 'high'],
            ['timetable_ready', 'Timetable conflict-free and publish-ready', 'Groups, rooms, faculty availability, locked slots, and conflict checks must pass.', 'critical'],
            ['assessment_ready', 'Assessment components and internal calendar ready', 'Internal assessment components, marks windows, and review dates must be configured.', 'medium'],
            ['mentor_student_risk_ready', 'Mentors and student-risk review ready', 'Mentor assignments, intervention queues, parent escalations, and retention-risk checks must be assigned.', 'medium'],
            ['resources_ready', 'Classroom, lab, and LMS resources ready', 'Rooms, labs, LMS/material readiness, and delivery resources must be confirmed.', 'medium'],
        ];
    }

    private function recommendedStudentIntervention(string $band, array $reasons): string
    {
        $actions = ['mentor meeting', 'weekly PMC review'];
        if (in_array('Attendance below 75%', $reasons, true)) {
            $actions[] = 'attendance warning';
            $actions[] = 'parent call';
        }
        if (in_array('Average marks below 45', $reasons, true)) {
            $actions[] = 'remedial class';
            $actions[] = 'faculty follow-up';
        }
        if (in_array('Open grievance', $reasons, true)) {
            $actions[] = 'grievance escalation review';
        }
        if ($band === 'critical') {
            $actions[] = 'program director review';
        }

        return 'Recommended actions: ' . implode(', ', array_unique($actions)) . '.';
    }

    private function recommendedDeliveryAction(AcademicPmcCourseDeliveryCheckpoint $checkpoint): string
    {
        $actions = [];
        $reasons = $checkpoint->signals['reasons'] ?? [];
        if (in_array('Planned sessions not conducted', $reasons, true)) {
            $actions[] = 'schedule makeup sessions';
        }
        if (in_array('Low attendance in delivered sessions', $reasons, true)) {
            $actions[] = 'coordinate attendance intervention';
        }
        if (in_array('Marks pending', $reasons, true)) {
            $actions[] = 'collect pending internal marks';
        }
        if (in_array('Low course feedback', $reasons, true)) {
            $actions[] = 'create feedback improvement plan';
        }
        if (empty($actions)) {
            $actions[] = 'review delivery checkpoint';
        }

        return 'Recommended actions: ' . implode(', ', array_unique($actions)) . '.';
    }

    private function selectorOptions(): array
    {
        return [
            'programs' => \App\Models\Program::orderBy('name')->limit(100)->get(['id', 'name', 'code']),
            'batches' => \App\Models\Batch::with('program')->latest()->limit(100)->get(['id', 'program_id', 'name', 'code']),
            'terms' => \App\Models\Term::with(['program', 'batch'])->latest()->limit(100)->get(['id', 'program_id', 'batch_id', 'name', 'term_number']),
            'users' => User::role(['pmc_head', 'pmc_manager', 'pmc_officer', 'program_director', 'program_leader', 'faculty_mentor'])->orderBy('name')->limit(100)->get(['id', 'name', 'email']),
            'students' => Student::with('user')->where('status', 'active')->latest()->limit(150)->get(['id', 'user_id', 'program_id', 'batch_id', 'guardian_name', 'guardian_phone']),
        ];
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
