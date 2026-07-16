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

class AcademicsPmcV003DemoSeeder extends Seeder
{
    public function seedPmcV003Signals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?int $termId): void
    {
        if (! $program) {
            return;
        }

        $batch = Batch::where('program_id', $program->id)->first();
        $teacher = Teacher::first();
        $term = $termId ? Term::find($termId) : Term::where('program_id', $program->id)->orWhere('batch_id', $batch?->id)->first();

        foreach ([
            ['curriculum', 'Close CO/PO mapping gap for analytics module', 'high', 'open', 'high', now()->addDays(2)],
            ['faculty', 'Resolve analytics lab overload and adjunct requirement', 'critical', 'open', 'critical', now()->addDay()],
            ['timetable', 'Freeze Term 1 timetable after conflict resolution', 'high', 'in_progress', 'high', now()->addDays(3)],
            ['student_success', 'Review at-risk cohort intervention plan', 'high', 'open', 'critical', now()->addDays(2)],
            ['review_action', 'Publish PMC weekly action closure note', 'normal', 'open', 'normal', now()->addDays(5)],
        ] as [$type, $title, $priority, $status, $severity, $due]) {
            AcademicPmcWorkItem::firstOrCreate(
                ['work_type' => $type, 'title' => $title],
                [
                    'description' => 'Seeded PMC OS v0.03 operating work item.',
                    'program_id' => $program->id,
                    'batch_id' => $batch?->id,
                    'term_id' => $term?->id,
                    'subject_id' => $subject?->id,
                    'student_id' => $student?->id,
                    'teacher_id' => $teacher?->id,
                    'owner_user_id' => $pmcHead->id,
                    'assigned_by' => $pmcHead->id,
                    'priority' => $priority,
                    'status' => $status,
                    'severity' => $severity,
                    'due_at' => $due,
                    'source_type' => $type,
                    'source_key' => 'pmc_v003_demo_' . $type,
                    'metrics' => ['score' => $severity === 'critical' ? 90 : 70],
                    'metadata' => ['version' => 'Academics PMC OS v0.03'],
                ]
            );
        }

        foreach ([
            ['PGDM Curriculum Rollout 2026', 'pmc_review', 68],
            ['Analytics Elective Basket Revision', 'dean_review', 74],
            ['Term 1 Course Basket Freeze', 'approved', 92],
        ] as [$title, $approval, $score]) {
            AcademicPmcCurriculumPlan::firstOrCreate(
                ['title' => $title, 'program_id' => $program->id],
                [
                    'batch_id' => $batch?->id,
                    'term_id' => $term?->id,
                    'owner_user_id' => $pmcHead->id,
                    'status' => $approval === 'approved' ? 'active' : 'draft',
                    'approval_status' => $approval,
                    'readiness_score' => $score,
                    'credit_rules' => ['min_credits' => 20, 'max_credits' => 28],
                    'obe_requirements' => ['co_po_mapping_required' => true, 'attainment_target' => 70],
                    'compliance_rules' => ['regulatory_body' => 'University', 'evidence_required' => true],
                    'rollout_due_at' => now()->addDays(10),
                    'metadata' => ['version' => 'Academics PMC OS v0.03'],
                ]
            );
        }
        app(AcademicPmcV003Service::class)->refreshCurriculumValidations($pmcHead);

        AcademicPmcFacultyLoadPlan::firstOrCreate(
            ['teacher_id' => $teacher?->id, 'program_id' => $program->id],
            [
                'term_id' => $term?->id,
                'owner_user_id' => $pmcHead->id,
                'planned_hours' => 18,
                'allocated_hours' => 27,
                'mentoring_load' => 36,
                'exam_load' => 8,
                'load_band' => 'critical',
                'status' => 'dean_review',
                'adjunct_required' => true,
                'constraints' => ['available_days' => ['Mon', 'Tue', 'Thu'], 'lab_required' => true],
                'metadata' => ['version' => 'Academics PMC OS v0.03'],
            ]
        );

        AcademicPmcTimetableControl::firstOrCreate(
            ['program_id' => $program->id, 'title' => 'Term 1 Timetable Freeze Control'],
            [
                'batch_id' => $batch?->id,
                'term_id' => $term?->id,
                'status' => 'conflict_review',
                'draft_slots' => 12,
                'published_slots' => 34,
                'teacher_conflicts' => 2,
                'room_conflicts' => 1,
                'freeze_due_at' => now()->addDays(3),
                'metadata' => ['version' => 'Academics PMC OS v0.03'],
            ]
        );

        AcademicPmcStudentSuccessPlan::firstOrCreate(
            ['student_id' => $student?->id, 'risk_type' => 'attendance_performance_correlation'],
            [
                'program_id' => $program->id,
                'batch_id' => $batch?->id,
                'mentor_user_id' => $pmcHead->id,
                'risk_band' => 'critical',
                'status' => 'intervention_due',
                'intervention_plan' => 'Mentor meeting, parent call, remedial plan, and weekly PMC review.',
                'next_review_at' => now()->addDays(4),
                'parent_escalation_required' => true,
                'signals' => ['attendance' => 'low', 'marks' => 'weak', 'mentor_meeting' => 'missed'],
                'metadata' => ['version' => 'Academics PMC OS v0.03'],
            ]
        );

        foreach ([
            ['PMC Weekly Operating Review', 'weekly_pmc', now()->addDays(1)],
            ['Curriculum Rollout Review', 'curriculum_review', now()->addDays(3)],
            ['Faculty Load Review', 'faculty_review', now()->addDays(4)],
            ['Timetable Freeze Review', 'timetable_review', now()->addDays(5)],
            ['Student Success Review', 'student_success_review', now()->addDays(6)],
        ] as [$title, $type, $date]) {
            AcademicPmcReviewMeeting::firstOrCreate(
                ['title' => $title, 'review_type' => $type],
                [
                    'scheduled_for' => $date,
                    'chair_user_id' => $pmcHead->id,
                    'status' => 'scheduled',
                    'agenda' => 'Review risks, blockers, decisions, and action ownership.',
                    'metadata' => ['version' => 'Academics PMC OS v0.03'],
                ]
            );
        }

        foreach ([
            ['PMC Default Command', 'command', ['severity' => ['critical', 'high']]],
            ['Faculty Overload', 'faculty', ['load_band' => 'critical']],
            ['Student Success Critical', 'student_success', ['risk_band' => 'critical']],
            ['Timetable Conflict Review', 'timetable', ['status' => 'conflict_review']],
        ] as [$name, $surface, $filters]) {
            AcademicPmcSavedView::firstOrCreate(
                ['user_id' => $pmcHead->id, 'name' => $name, 'surface' => $surface],
                ['filters' => $filters, 'is_default' => $surface === 'command']
            );
        }

        AcademicPmcExportLog::firstOrCreate(
            ['user_id' => $pmcHead->id, 'report_key' => 'workbench'],
            ['filters' => ['demo' => true], 'row_count' => 5, 'exported_at' => now(), 'metadata' => ['version' => 'Academics PMC OS v0.03']]
        );

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_pmc_os_v003_seeded',
                'description' => 'Seeded Academics PMC OS v0.03 command, workbench, curriculum, faculty, timetable, student success, reviews, saved views, and exports.',
            ],
            [
                'actor_user_id' => $pmcHead->id,
                'metadata' => ['version' => 'Academics PMC OS v0.03'],
            ]
        );
    }

}
