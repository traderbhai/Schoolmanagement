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

class AcademicsDeanOperatingDemoSeeder extends Seeder
{
    public function seedDeanOperatingSignals(Department $department, User $dean, User $pmcHead, User $coe, User $iqacHead, ?Program $program): void
    {
        $meetings = [
            ['Weekly Academic Review', 'weekly_academic', now()->addDay()],
            ['Exam Readiness Review', 'exam_review', now()->addDays(2)],
            ['IQAC Quality Review', 'iqac_review', now()->addDays(3)],
            ['Admission Handoff Review', 'handoff_review', now()->addDays(4)],
        ];

        foreach ($meetings as [$title, $type, $date]) {
            AcademicDeanReviewMeeting::firstOrCreate(
                ['title' => $title, 'review_type' => $type],
                [
                    'scheduled_for' => $date,
                    'chaired_by' => $dean->id,
                    'scope_type' => $program ? 'program' : 'department',
                    'scope_id' => $program?->id,
                    'status' => 'scheduled',
                    'summary' => 'Seeded Dean OS v0.07 review agenda.',
                    'metadata' => ['version' => 'Academics OS v0.07'],
                ]
            );
        }

        $weekly = AcademicDeanReviewMeeting::where('title', 'Weekly Academic Review')->first();
        foreach ([
            ['PMC curriculum action overdue', 'pmc', $pmcHead->id, 'critical', now()->subDay(), 'Close pending curriculum readiness and mapping action.'],
            ['CoE marks pending action', 'coe', $coe->id, 'high', now()->addDay(), 'Follow up pending marks and result publication.'],
            ['IQAC OBE mapping action', 'iqac', $iqacHead->id, 'high', now()->addDays(2), 'Resolve OBE mapping and attainment evidence gaps.'],
            ['Program student risk action', 'program', $pmcHead->id, 'high', now()->addDays(3), 'Review attendance and weak performance intervention plan.'],
            ['Course delivery feedback action', 'course_delivery', $pmcHead->id, 'normal', now()->addDays(4), 'Create feedback closure note for low-rated course delivery.'],
            ['Handoff blocker action', 'handoff', $dean->id, 'critical', now()->subHours(4), 'Clear blocked Admission to Academics handoff queue.'],
        ] as [$title, $source, $owner, $priority, $due, $description]) {
            AcademicDeanActionItem::firstOrCreate(
                ['title' => $title, 'source_type' => $source],
                [
                    'meeting_id' => $weekly?->id,
                    'description' => $description,
                    'source_key' => $source . '_demo',
                    'owner_user_id' => $owner,
                    'assigned_by' => $dean->id,
                    'priority' => $priority,
                    'due_at' => $due,
                    'status' => 'open',
                    'metadata' => ['program_id' => $program?->id, 'version' => 'Academics OS v0.07'],
                ]
            );
        }

        foreach ([
            ['Dean Default Dashboard', 'dashboard', ['band' => ['critical', 'high']]],
            ['Critical Program Risk', 'program_risk', ['risk_band' => 'critical']],
            ['Blocked Handoffs', 'handoff', ['status' => ['blocked', 'returned_for_correction']]],
        ] as [$name, $surface, $filters]) {
            AcademicDeanSavedView::firstOrCreate(
                ['name' => $name, 'surface' => $surface, 'user_id' => $dean->id],
                ['filters' => $filters, 'is_default' => $surface === 'dashboard']
            );
        }

        AcademicDeanExportLog::firstOrCreate(
            ['user_id' => $dean->id, 'report_key' => 'branch_health'],
            ['filters' => ['demo' => true], 'row_count' => 5, 'exported_at' => now(), 'metadata' => ['version' => 'Academics OS v0.07']]
        );

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v007_seeded',
                'description' => 'Seeded Academics OS v0.07 Dean command data for reviews, actions, saved views, exports, and branch health.',
            ],
            [
                'actor_user_id' => $dean->id,
                'metadata' => ['version' => 'Academics OS v0.07'],
            ]
        );
    }

}
