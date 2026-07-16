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

class AcademicsIqacOperatingDemoSeeder extends Seeder
{
    public function seedIqacOperatingSignals(Department $department, User $iqacHead, ?Program $program, ?Subject $subject, ?Student $student, ?int $termId): void
    {
        if (! $program || ! $subject) {
            return;
        }

        if (! $termId) {
            $batch = Batch::firstOrCreate(
                ['code' => ($program->code ?: 'ACAD') . '-IQAC-DEMO'],
                [
                    'program_id' => $program->id,
                    'name' => ($program->code ?: $program->name) . ' IQAC Demo Batch',
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->addYear()->endOfYear()->toDateString(),
                    'intake_capacity' => 60,
                    'status' => 'active',
                ]
            );

            $termId = Term::firstOrCreate(
                ['batch_id' => $batch->id, 'term_number' => 1],
                [
                    'program_id' => $program->id,
                    'name' => 'Term 1',
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->addMonths(4)->toDateString(),
                    'is_current' => true,
                    'sort_order' => 1,
                ]
            )->id;
        }

        $po = ProgramOutcome::firstOrCreate(
            ['program_id' => $program->id, 'code' => 'PO-IQAC-1'],
            ['description' => 'Apply analytical thinking and ethical decision-making in professional contexts.', 'category' => 'management', 'is_active' => true]
        );

        ProgramSpecificOutcome::firstOrCreate(
            ['program_id' => $program->id, 'code' => 'PSO-IQAC-1'],
            ['description' => 'Demonstrate industry-ready problem solving in management practice.', 'is_active' => true]
        );

        $coMapped = CourseOutcome::firstOrCreate(
            ['subject_id' => $subject->id, 'code' => 'CO-IQAC-1'],
            ['description' => 'Analyze management cases using structured frameworks.', 'bloom_level' => 'analyze', 'is_active' => true]
        );

        $coGap = CourseOutcome::firstOrCreate(
            ['subject_id' => $subject->id, 'code' => 'CO-IQAC-GAP'],
            ['description' => 'Prepare decision recommendations for complex scenarios.', 'bloom_level' => 'evaluate', 'is_active' => true]
        );

        CoPoMapping::firstOrCreate(
            ['course_outcome_id' => $coMapped->id, 'program_outcome_id' => $po->id],
            ['program_specific_outcome_id' => null, 'correlation_level' => 3]
        );

        CoAttainment::firstOrCreate(
            ['course_outcome_id' => $coMapped->id, 'term_id' => $termId, 'batch_id' => null],
            [
                'subject_id' => $subject->id,
                'direct_attainment' => 52,
                'indirect_attainment' => 58,
                'final_attainment' => 54,
                'target_attainment' => 60,
                'target_met' => false,
                'students_assessed' => 42,
                'students_attained' => 22,
            ]
        );

        PoAttainment::firstOrCreate(
            ['program_outcome_id' => $po->id, 'program_id' => $program->id, 'term_id' => $termId],
            ['attainment_value' => 55, 'target_value' => 60, 'target_met' => false]
        );

        $survey = ObeSurvey::firstOrCreate(
            ['subject_id' => $subject->id, 'term_id' => $termId, 'title' => 'IQAC Indirect Attainment Survey'],
            ['is_published' => true, 'closes_at' => now()->addDays(14)->toDateString()]
        );

        if ($student) {
            ObeSurveyResponse::firstOrCreate(
                ['obe_survey_id' => $survey->id, 'student_id' => $student->id, 'course_outcome_id' => $coMapped->id],
                ['rating' => 3]
            );

            CourseFeedback::firstOrCreate(
                ['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $termId],
                [
                    'teaching_rating' => 3,
                    'content_rating' => 3,
                    'overall_rating' => 3,
                    'comments' => 'Needs more applied quality evidence and feedback closure.',
                    'is_anonymous' => true,
                ]
            );
        }

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'quality_audit_review',
                'description' => 'IQAC reviewed OBE mapping, attainment target misses, and feedback action-plan evidence.',
            ],
            [
                'actor_user_id' => $iqacHead->id,
                'metadata' => ['version' => 'Academics OS v0.04'],
            ]
        );

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v004_seeded',
                'description' => 'Seeded Academics OS v0.04 IQAC operating data for OBE, attainment, surveys, feedback, and audit compliance.',
            ],
            [
                'actor_user_id' => $iqacHead->id,
                'metadata' => ['version' => 'Academics OS v0.04'],
            ]
        );
    }

}
