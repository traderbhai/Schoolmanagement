<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanApprovalItem;
use App\Models\AcademicDeanOperatingRecord;
use App\Models\AcademicDeanPlanningCycle;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicDeanCommandService
{
    public function __construct(
        private AcademicPmcOperatingService $pmc,
        private AcademicCoeOperatingService $coe,
        private AcademicIqacOperatingService $iqac,
        private AcademicProgramLeadershipService $programs,
        private AcademicCourseDeliveryService $courses,
        private AcademicDeanAttentionService $attention,
        private AcademicDeanRiskService $risk
    ) {}

    public function dashboard(User $user): array
    {
        $queues = $this->attention->queues();
        $risks = $this->risk->programRisks();
        $branchHealth = $this->branchHealth($user);
        $critical = collect($queues)->sum(fn ($queue) => collect($queue['items'])->whereIn('severity', ['critical', 'high'])->count());

        return [
            'kpis' => [
                'overdue_approvals' => $queues['overdue_dean_approvals']['count'] ?? 0,
                'open_actions' => AcademicDeanActionItem::whereNotIn('status', ['done', 'cancelled'])->count(),
                'critical_program_risks' => $risks->whereIn('band', ['critical', 'high'])->count(),
                'handoff_blockers' => $queues['admission_handoff_blockers']['count'] ?? 0,
                'critical_attention' => $critical,
            ],
            'todayPriority' => $this->topPriority($queues, $risks),
            'branchHealth' => $branchHealth,
            'attentionQueues' => $queues,
            'criticalItems' => $this->attention->criticalItems(),
            'programRisks' => $risks,
            'reviews' => AcademicDeanReviewMeeting::with('chair')->latest('scheduled_for')->limit(6)->get(),
            'actions' => AcademicDeanActionItem::with('owner')->whereNotIn('status', ['done', 'cancelled'])->orderBy('due_at')->limit(10)->get(),
            'reports' => $this->reports($user),
        ];
    }

    public function branchHealth(User $user): Collection
    {
        $pmc = $this->pmc->dashboard($user);
        $coe = $this->coe->dashboard($user);
        $iqac = $this->iqac->dashboard($user);
        $programs = $this->programs->dashboard($user);
        $courses = $this->courses->dashboard($user);

        return collect([
            $this->branch('PMC', 'pmc', route('academics.pmc.index'), $pmc['kpis']['curriculum_gaps'] + $pmc['kpis']['faculty_gaps'], [
                'Curriculum gaps' => $pmc['kpis']['curriculum_gaps'],
                'Faculty gaps' => $pmc['kpis']['faculty_gaps'],
                'Student risk' => $pmc['kpis']['student_risk'],
            ]),
            $this->branch('CoE / Examination', 'coe', route('academics.coe.index'), $coe['kpis']['marks_pending'] + $coe['kpis']['hall_ticket_blocks'], [
                'Upcoming exams' => $coe['kpis']['upcoming_exams'],
                'Marks pending' => $coe['kpis']['marks_pending'],
                'Hall ticket blocks' => $coe['kpis']['hall_ticket_blocks'],
            ]),
            $this->branch('IQAC', 'iqac', route('academics.iqac.index'), $iqac['kpis']['obe_gaps'] + $iqac['kpis']['target_misses'], [
                'OBE gaps' => $iqac['kpis']['obe_gaps'],
                'Target misses' => $iqac['kpis']['target_misses'],
                'Feedback gaps' => $iqac['kpis']['feedback_gaps'],
            ]),
            $this->branch('Program Leadership', 'program', route('academics.program-leadership.index'), $programs['kpis']['delivery_gaps'] + $programs['kpis']['student_risk'], [
                'Programs' => $programs['kpis']['programs'],
                'Delivery gaps' => $programs['kpis']['delivery_gaps'],
                'Student risk' => $programs['kpis']['student_risk'],
            ]),
            $this->branch('Course Delivery', 'course_delivery', route('academics.course-delivery.index'), $courses['kpis']['attendance_risk'] + $courses['kpis']['mentor_actions'], [
                'Assigned courses' => $courses['kpis']['assigned_courses'],
                'Today sessions' => $courses['kpis']['today_sessions'],
                'Attendance risk' => $courses['kpis']['attendance_risk'],
            ]),
        ]);
    }

    public function reports(User $user): Collection
    {
        $queues = $this->attention->queues();

        return collect([
            ['key' => 'branch_health', 'label' => 'Dean branch health', 'count' => $this->branchHealth($user)->count(), 'route' => route('academics.dean-os.branch-health')],
            ['key' => 'program_risk', 'label' => 'Program risk heatmap', 'count' => $this->risk->programRisks()->whereIn('band', ['critical', 'high'])->count(), 'route' => route('academics.dean-os.program-risk', ['band' => 'critical_high'])],
            ['key' => 'approval_sla', 'label' => 'Approval SLA', 'count' => ($queues['overdue_dean_approvals']['count'] ?? 0) + ($queues['pending_dean_approvals']['count'] ?? 0), 'route' => route('academics.dean-os.attention', 'pending_dean_approvals')],
            ['key' => 'academic_actions', 'label' => 'Academic action tracker', 'count' => AcademicDeanActionItem::whereNotIn('status', ['done', 'cancelled'])->count(), 'route' => route('academics.dean-os.reviews', ['status' => 'open'])],
            ['key' => 'handoff_readiness', 'label' => 'Admission handoff readiness', 'count' => $queues['admission_handoff_blockers']['count'] ?? 0, 'route' => route('academics.dean-os.handoff', ['status' => 'blocking'])],
            ['key' => 'course_delivery_gaps', 'label' => 'Course delivery gaps', 'count' => $queues['course_delivery_gaps']['count'] ?? 0, 'route' => route('academics.course-delivery.index')],
            ['key' => 'academic_planning', 'label' => 'Academic planning cycles', 'count' => AcademicDeanPlanningCycle::count(), 'route' => route('academics.dean-os.planning.index')],
            ['key' => 'approval_cockpit', 'label' => 'Dean approval cockpit', 'count' => AcademicDeanApprovalItem::where('status', 'pending')->count(), 'route' => route('academics.dean-os.approval-cockpit.index')],
            ['key' => 'faculty_workload', 'label' => 'Faculty workload governance', 'count' => AcademicDeanOperatingRecord::where('record_type', 'faculty_workload')->count(), 'route' => route('academics.dean-os.faculty-workload.index')],
            ['key' => 'student_success', 'label' => 'Student success command', 'count' => AcademicDeanOperatingRecord::where('record_type', 'student_success')->count(), 'route' => route('academics.dean-os.student-success.index')],
            ['key' => 'exam_readiness', 'label' => 'Exam readiness command', 'count' => AcademicDeanOperatingRecord::where('record_type', 'exam_readiness')->count(), 'route' => route('academics.dean-os.exam-readiness.index')],
            ['key' => 'quality_command', 'label' => 'IQAC quality command', 'count' => AcademicDeanOperatingRecord::where('record_type', 'quality_command')->count(), 'route' => route('academics.dean-os.quality-command.index')],
            ['key' => 'induction_onboarding', 'label' => 'Induction and onboarding', 'count' => AcademicDeanOperatingRecord::where('record_type', 'induction_onboarding')->count(), 'route' => route('academics.dean-os.induction.index')],
            ['key' => 'policy_audit', 'label' => 'Dean policy audit', 'count' => \App\Models\AcademicDeanPolicyAudit::count(), 'route' => route('academics.dean-os.policy-audit.index')],
        ]);
    }

    private function branch(string $label, string $key, string $route, int $risk, array $metrics): array
    {
        return [
            'label' => $label,
            'key' => $key,
            'route' => $route,
            'risk' => $risk,
            'band' => $risk >= 10 ? 'critical' : ($risk >= 4 ? 'high' : ($risk >= 1 ? 'medium' : 'low')),
            'metrics' => $metrics,
            'open_actions' => AcademicDeanActionItem::where('source_type', $key)->whereNotIn('status', ['done', 'cancelled'])->count(),
            'overdue_actions' => AcademicDeanActionItem::where('source_type', $key)->whereNotIn('status', ['done', 'cancelled'])->where('due_at', '<', now())->count(),
        ];
    }

    private function topPriority(array $queues, Collection $risks): array
    {
        $critical = collect($queues)->flatMap(fn ($queue) => $queue['items'])->where('severity', 'critical')->first();
        if ($critical) {
            return ['title' => $critical['title'], 'body' => $critical['subtitle'], 'route' => $critical['route'], 'action' => $critical['action'], 'level' => 'danger'];
        }

        $risk = $risks->whereIn('band', ['critical', 'high'])->first();
        if ($risk) {
            return ['title' => 'Review ' . $risk['program']->code . ' academic risk', 'body' => $risk['reasons']->join(', '), 'route' => $risk['route'], 'action' => 'Open risk heatmap', 'level' => 'warning'];
        }

        return ['title' => 'No urgent Dean action today', 'body' => 'Review branch health, action closures, and upcoming academic calendar priorities.', 'route' => route('academics.dean-os.branch-health'), 'action' => 'Review branch health', 'level' => 'primary'];
    }
}
