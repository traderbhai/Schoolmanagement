<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\AcademicTranscript;
use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanActionEvidence;
use App\Models\AcademicDeanApprovalItem;
use App\Models\AcademicDeanCalendarEvent;
use App\Models\AcademicDeanDecision;
use App\Models\AcademicDeanExportLog;
use App\Models\AcademicDeanMeetingMinute;
use App\Models\AcademicDeanOperatingRecord;
use App\Models\AcademicDeanPlanningCycle;
use App\Models\AcademicDeanPolicyAudit;
use App\Models\AcademicDeanReadinessItem;
use App\Models\AcademicDeanReportPack;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\AcademicDeanReviewTemplate;
use App\Models\AcademicDeanRiskMitigation;
use App\Models\AcademicDeanRiskSnapshot;
use App\Models\AcademicDeanRiskThreshold;
use App\Models\AcademicDeanSavedView;
use App\Models\AcademicPmcCurriculumPlan;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcFacultyLoadPlan;
use App\Models\AcademicPmcAnalyticsSnapshot;
use App\Models\AcademicPmcActionDependency;
use App\Models\AcademicPmcActionEvidence;
use App\Models\AcademicPmcActionReminder;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcAutomationExecution;
use App\Models\AcademicPmcAutomationRule;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseDeliveryCheckpoint;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\AcademicPmcFacultyAvailabilityRequest;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupBuildRun;
use App\Models\AcademicPmcGroupDeliveryTracker;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcPolicyAudit;
use App\Models\AcademicPmcPlanningCycle;
use App\Models\AcademicPmcParentEscalation;
use App\Models\AcademicPmcReadinessItem;
use App\Models\AcademicPmcRemedialAction;
use App\Models\AcademicPmcReviewMeeting;
use App\Models\AcademicPmcReviewGovernanceRecord;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcSessionDeliveryLog;
use App\Models\AcademicPmcStudentBasketAcknowledgement;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcStudentIntervention;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableControl;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\AcademicPmcWorkItem;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseFeedback;
use App\Models\CoAttainment;
use App\Models\CoPoMapping;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\ElectiveRegistrationWindow;
use App\Models\Exam;
use App\Models\ExamAnomalyLog;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\LeaveApplication;
use App\Models\MarksAppeal;
use App\Models\MentorMeeting;
use App\Models\MentorMessage;
use App\Models\ObeSurvey;
use App\Models\ObeSurveyResponse;
use App\Models\PoAttainment;
use App\Models\PmcAssessmentComponentConfig;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\ProgramSpecificOutcome;
use App\Models\ProgramSubject;
use App\Models\RoleProgramAssignment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use App\Models\SubjectDiscussion;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\AcademicScopeService;
use App\Services\AcademicPmcV003Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AcademicsDeanV008DemoSeeder extends Seeder
{
    public function seedDeanV008Signals(Department $department, User $dean, User $pmcHead, User $coe, User $iqacHead, ?Program $program, ?Subject $subject, ?Student $student): void
    {
        $teacher = Teacher::first();
        $batch = $program ? Batch::where('program_id', $program->id)->first() : Batch::first();
        $term = $program ? Term::where('program_id', $program->id)->orWhere('batch_id', $batch?->id)->first() : Term::first();

        foreach ([
            ['Annual Academic Plan 2026-27', 'annual_plan', 'draft', 25],
            ['Semester 1 Readiness Plan', 'semester_readiness', 'dean_review', 62],
            ['Approved Academic Calendar 2026', 'academic_calendar', 'published', 100],
            ['Teaching Load Approval Cycle', 'teaching_load', 'dean_review', 70],
        ] as [$title, $type, $status, $score]) {
            $cycle = AcademicDeanPlanningCycle::firstOrCreate(
                ['title' => $title, 'cycle_type' => $type],
                [
                    'academic_year' => '2026-27',
                    'program_id' => $program?->id,
                    'batch_id' => $batch?->id,
                    'term_id' => $term?->id,
                    'branch' => $type === 'teaching_load' ? 'program_leadership' : 'dean_office',
                    'owner_user_id' => $type === 'academic_calendar' ? $dean->id : $pmcHead->id,
                    'status' => $status,
                    'readiness_score' => $score,
                    'starts_at' => now()->startOfMonth(),
                    'ends_at' => now()->addMonths(6),
                    'metadata' => ['version' => 'Academics OS v0.08'],
                ]
            );

            foreach ([
                ['curriculum_readiness', 'Curriculum and syllabus signed off', 'done', false],
                ['faculty_allocation', 'Faculty allocation gap for analytics lab', 'blocked', true],
                ['timetable_readiness', 'Timetable freeze pending for Term 1', 'pending', true],
                ['classroom_lab_readiness', 'Analytics lab capacity review', 'pending', false],
                ['lms_material_readiness', 'LMS reading pack upload', 'pending', false],
                ['assessment_plan_readiness', 'Internal assessment plan review', 'pending', false],
                ['mentoring_readiness', 'Mentor allocation for new cohort', 'pending', false],
                ['admission_handoff_readiness', 'Admission handoff blockers review', 'blocked', true],
            ] as [$section, $itemTitle, $itemStatus, $blocker]) {
                AcademicDeanReadinessItem::firstOrCreate(
                    ['planning_cycle_id' => $cycle->id, 'section' => $section],
                    [
                        'title' => $itemTitle,
                        'owner_user_id' => $blocker ? $pmcHead->id : $dean->id,
                        'status' => $itemStatus,
                        'is_blocker' => $blocker,
                        'due_at' => now()->addDays($blocker ? 2 : 8),
                        'source_type' => 'planning',
                        'source_key' => $type,
                        'metadata' => ['version' => 'Academics OS v0.08'],
                    ]
                );
            }
        }

        foreach ([
            ['Weekly Academic Review Template', 'weekly_academic', 'weekly'],
            ['Program Review Template', 'program_review', 'monthly'],
            ['Exam Review Template', 'exam_review', 'term_wise'],
            ['IQAC Review Template', 'iqac_review', 'monthly'],
            ['Student Success Review Template', 'student_success_review', 'fortnightly'],
            ['Handoff Review Template', 'handoff_review', 'weekly'],
            ['Emergency Academic Review Template', 'emergency_review', 'custom'],
        ] as [$name, $type, $recurrence]) {
            AcademicDeanReviewTemplate::firstOrCreate(
                ['name' => $name],
                [
                    'review_type' => $type,
                    'recurrence' => $recurrence,
                    'agenda_items' => ['risks', 'approvals', 'actions', 'blockers', 'decisions'],
                    'metadata' => ['version' => 'Academics OS v0.08'],
                ]
            );
        }

        $meeting = AcademicDeanReviewMeeting::where('title', 'Weekly Academic Review')->first();
        if ($meeting) {
            $minute = AcademicDeanMeetingMinute::firstOrCreate(
                ['meeting_id' => $meeting->id],
                [
                    'minutes' => 'Reviewed faculty load, student success risks, exam readiness, and IQAC evidence gaps. Create follow-up actions for pending blockers.',
                    'status' => 'approved',
                    'submitted_by' => $pmcHead->id,
                    'approved_by' => $dean->id,
                    'approved_at' => now()->subDay(),
                    'metadata' => ['version' => 'Academics OS v0.08'],
                ]
            );

            AcademicDeanDecision::firstOrCreate(
                ['meeting_id' => $meeting->id, 'title' => 'Approve bridge course for admitted cohort'],
                [
                    'decision_type' => 'induction',
                    'program_id' => $program?->id,
                    'batch_id' => $batch?->id,
                    'term_id' => $term?->id,
                    'owner_user_id' => $pmcHead->id,
                    'status' => 'open',
                    'due_at' => now()->addDays(5),
                    'evidence' => 'Bridge course plan to be attached by Program Leadership.',
                    'metadata' => ['minute_id' => $minute->id, 'version' => 'Academics OS v0.08'],
                ]
            );
        }

        $action = AcademicDeanActionItem::firstOrCreate(
            ['title' => 'Verify evidence for timetable freeze', 'source_type' => 'action_governance'],
            [
                'description' => 'Evidence-backed closure required before timetable can be treated as frozen.',
                'source_key' => 'timetable_freeze',
                'owner_user_id' => $pmcHead->id,
                'assigned_by' => $dean->id,
                'priority' => 'critical',
                'due_at' => now()->subDay(),
                'status' => 'blocked',
                'metadata' => ['program_id' => $program?->id, 'requires_evidence' => true, 'version' => 'Academics OS v0.08'],
            ]
        );

        AcademicDeanActionEvidence::firstOrCreate(
            ['action_item_id' => $action->id, 'title' => 'Draft timetable freeze note'],
            [
                'uploaded_by' => $pmcHead->id,
                'path' => 'demo/timetable-freeze-note.pdf',
                'notes' => 'Pending Dean verification.',
                'verification_status' => 'pending',
            ]
        );

        AcademicDeanRiskThreshold::firstOrCreate(
            ['dimension' => 'overall', 'scope_type' => 'department', 'scope_id' => null],
            ['medium_threshold' => 20, 'high_threshold' => 40, 'critical_threshold' => 70, 'is_active' => true, 'metadata' => ['version' => 'Academics OS v0.08']]
        );

        $snapshot = AcademicDeanRiskSnapshot::firstOrCreate(
            ['program_id' => $program?->id, 'snapshot_date' => now()->toDateString(), 'branch' => 'program_leadership'],
            [
                'batch_id' => $batch?->id,
                'term_id' => $term?->id,
                'score' => 76,
                'band' => 'critical',
                'trend' => 'worsening',
                'metrics' => ['attendance' => 18, 'faculty_workload' => 22, 'exam_readiness' => 16, 'handoff' => 20],
                'reasons' => ['Attendance decline', 'Faculty overload', 'Exam readiness blocker', 'Handoff pending'],
            ]
        );

        AcademicDeanRiskMitigation::firstOrCreate(
            ['risk_snapshot_id' => $snapshot->id, 'plan' => 'Dean mitigation plan for critical academic risk'],
            ['owner_user_id' => $pmcHead->id, 'status' => 'mitigation_planned', 'due_at' => now()->addDays(6), 'metadata' => ['version' => 'Academics OS v0.08']]
        );

        foreach ([
            ['curriculum_change', 'Approve revised analytics syllabus', $pmcHead->id, 'pending', 'high'],
            ['academic_calendar', 'Publish academic calendar revision', $dean->id, 'pending', 'normal'],
            ['teaching_load', 'Approve overload for analytics lab faculty', $pmcHead->id, 'pending', 'critical'],
            ['exam_readiness', 'Return exam readiness blocker for evidence', $coe->id, 'pending', 'high'],
            ['iqac_gap', 'Approve OBE gap closure plan', $iqacHead->id, 'pending', 'high'],
            ['student_intervention', 'Approve parent escalation plan', $pmcHead->id, 'pending', 'normal'],
        ] as [$type, $title, $owner, $status, $risk]) {
            AcademicDeanApprovalItem::firstOrCreate(
                ['approval_type' => $type, 'title' => $title],
                [
                    'source_type' => $type,
                    'source_key' => 'demo_' . $type,
                    'owner_user_id' => $owner,
                    'status' => $status,
                    'risk_level' => $risk,
                    'due_at' => now()->addDays($risk === 'critical' ? 1 : 3),
                    'metadata' => ['version' => 'Academics OS v0.08'],
                ]
            );
        }

        foreach ([
            ['faculty_workload', 'Analytics faculty weekly load exceeds approved threshold', $program?->id, null, $teacher?->id, $pmcHead->id, 'critical', 88],
            ['faculty_performance', 'Course feedback below Dean threshold', $program?->id, null, $teacher?->id, $pmcHead->id, 'high', 52],
            ['mentoring_governance', 'Mentor load exceeds 35 students', $program?->id, null, $teacher?->id, $pmcHead->id, 'high', 70],
            ['student_success', 'Cohort attendance-performance correlation risk', $program?->id, $student?->id, null, $pmcHead->id, 'critical', 81],
            ['student_intervention', 'Parent escalation pending for at-risk student', $program?->id, $student?->id, null, $pmcHead->id, 'high', 64],
            ['retention_risk', 'Dropout early warning for repeated absence', $program?->id, $student?->id, null, $pmcHead->id, 'critical', 86],
            ['curriculum_governance', 'CO/PO mapping approval missing', $program?->id, null, null, $iqacHead->id, 'high', 72],
            ['syllabus_version', 'Syllabus v2 awaiting Dean comparison', $program?->id, null, null, $pmcHead->id, 'normal', 40],
            ['compliance_mapping', 'Credit structure validation requires evidence', $program?->id, null, null, $iqacHead->id, 'high', 68],
            ['exam_readiness', 'Question paper moderation pending', $program?->id, null, null, $coe->id, 'critical', 90],
            ['quality_command', 'NAAC evidence gap in criterion 2', $program?->id, null, null, $iqacHead->id, 'high', 75],
            ['audit_evidence', 'Audit evidence upload pending', $program?->id, null, null, $iqacHead->id, 'high', 66],
            ['obe_action_plan', 'OBE attainment target miss action plan', $program?->id, null, null, $iqacHead->id, 'high', 71],
            ['induction_onboarding', 'Section allocation pending for new admission cohort', $program?->id, $student?->id, null, $pmcHead->id, 'critical', 82],
            ['onboarding_readiness', 'Mentor assignment and bridge course pending', $program?->id, $student?->id, null, $pmcHead->id, 'high', 69],
        ] as [$type, $title, $programId, $studentId, $teacherId, $ownerId, $severity, $score]) {
            AcademicDeanOperatingRecord::firstOrCreate(
                ['record_type' => $type, 'title' => $title],
                [
                    'program_id' => $programId,
                    'batch_id' => $batch?->id,
                    'term_id' => $term?->id,
                    'student_id' => $studentId,
                    'teacher_id' => $teacherId,
                    'owner_user_id' => $ownerId,
                    'status' => in_array($severity, ['critical', 'high'], true) ? 'open' : 'under_review',
                    'severity' => $severity,
                    'score' => $score,
                    'due_at' => now()->addDays($severity === 'critical' ? 1 : 5),
                    'source_type' => $type,
                    'source_key' => 'demo_' . $type,
                    'metrics' => ['score' => $score, 'threshold' => 70],
                    'metadata' => ['version' => 'Academics OS v0.08'],
                ]
            );
        }

        foreach ([
            ['academic_plan_milestone', 'Annual plan Dean review', $dean->id, now()->addDay(), 'planning'],
            ['review_meeting', 'Weekly academic review', $dean->id, now()->addDays(2), 'reviews'],
            ['approval_deadline', 'Teaching load approval deadline', $pmcHead->id, now()->addDays(3), 'approval'],
            ['exam_milestone', 'Question paper moderation deadline', $coe->id, now()->addDays(4), 'exam'],
            ['iqac_audit', 'IQAC OBE evidence review', $iqacHead->id, now()->addDays(5), 'quality'],
            ['induction_event', 'New cohort orientation readiness', $pmcHead->id, now()->addDays(6), 'induction'],
            ['report_schedule', 'Weekly Dean review pack', $dean->id, now()->addDays(7), 'report'],
        ] as [$type, $title, $owner, $date, $source]) {
            AcademicDeanCalendarEvent::firstOrCreate(
                ['event_type' => $type, 'title' => $title],
                [
                    'owner_user_id' => $owner,
                    'program_id' => $program?->id,
                    'batch_id' => $batch?->id,
                    'term_id' => $term?->id,
                    'starts_at' => $date,
                    'ends_at' => (clone $date)->addHour(),
                    'status' => 'scheduled',
                    'source_type' => $source,
                    'source_key' => 'demo_' . $source,
                    'metadata' => ['version' => 'Academics OS v0.08'],
                ]
            );
        }

        foreach ([
            ['Weekly Dean Review Pack', 'weekly_dean_review', 'weekly'],
            ['Monthly Academic Risk Pack', 'monthly_academic_risk', 'monthly'],
            ['Exam Readiness Pack', 'exam_readiness', 'term_wise'],
            ['IQAC Quality Pack', 'iqac_quality', 'monthly'],
        ] as [$name, $type, $schedule]) {
            AcademicDeanReportPack::firstOrCreate(
                ['name' => $name],
                ['pack_type' => $type, 'schedule' => $schedule, 'status' => 'active', 'last_generated_at' => now()->subDays(3), 'filters' => ['program_id' => $program?->id], 'metadata' => ['version' => 'Academics OS v0.08']]
            );
        }

        foreach ([
            ['Planning Default', 'planning', ['status' => 'open']],
            ['Approval High Risk', 'approval_cockpit', ['risk_level' => ['high', 'critical']]],
            ['Faculty Overload', 'faculty_workload', ['severity' => 'critical']],
            ['Student Success Critical', 'student_success', ['severity' => 'critical']],
            ['Exam Readiness Blocks', 'exam_readiness', ['status' => 'open']],
            ['Policy Audit Writes', 'policy_audit', ['risk_level' => 'write']],
        ] as [$name, $surface, $filters]) {
            AcademicDeanSavedView::firstOrCreate(
                ['user_id' => $dean->id, 'name' => $name, 'surface' => $surface],
                ['filters' => $filters, 'is_default' => false]
            );
        }

        foreach ([
            ['academics.dean-os.planning.index', 'GET', 'read'],
            ['academics.dean-os.planning.store', 'POST', 'write'],
            ['academics.dean-os.approval-cockpit.decide', 'PATCH', 'write'],
            ['academics.dean-os.action-evidence.store', 'POST', 'write'],
            ['academics.dean-os.policy-audit.index', 'GET', 'read'],
        ] as [$route, $method, $risk]) {
            AcademicDeanPolicyAudit::firstOrCreate(
                ['route_name' => $route, 'method' => $method],
                ['expected_roles' => 'admin,director,academic_department_owner,dean_academics', 'risk_level' => $risk, 'has_policy' => true, 'last_test_status' => 'covered', 'notes' => 'Seeded v0.08 policy audit row.']
            );
        }

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v008_seeded',
                'description' => 'Seeded Academics OS v0.08 Dean planning, reviews, actions, risks, approvals, workload, student success, curriculum, exam, quality, induction, analytics, calendar, and policy audit data.',
            ],
            [
                'actor_user_id' => $dean->id,
                'metadata' => ['version' => 'Academics OS v0.08'],
            ]
        );
    }

}
