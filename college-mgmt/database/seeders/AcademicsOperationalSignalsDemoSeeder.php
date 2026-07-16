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

class AcademicsOperationalSignalsDemoSeeder extends Seeder
{
    public function seedOperationalSignals(Department $department, User $dean, User $pmcHead, User $coe, User $iqacHead): void
    {
        $program = Program::where('is_active', true)->first();
        $subject = Subject::where('is_active', true)->whereNotNull('program_id')->first()
            ?: Subject::where('is_active', true)->first();
        $student = Student::whereHas('user', fn ($query) => $query->where('email', 'arjun.k@demo.edu'))->first()
            ?: Student::first();
        $semester = Semester::first();
        $termId = $program ? Term::where('program_id', $program->id)->value('id') : null;

        if ($program && $subject) {
            $change = CurriculumChange::firstOrCreate(
                ['program_id' => $program->id, 'title' => 'Revise analytics module credits'],
                [
                    'subject_id' => $subject->id,
                    'proposed_by' => $pmcHead->id,
                    'description' => 'Increase applied analytics lab depth for the current curriculum cycle.',
                    'change_type' => 'modify_credits',
                    'before_state' => ['credits' => $subject->credits],
                    'after_state' => ['credits' => ($subject->credits ?? 3) + 1],
                    'status' => 'submitted',
                    'submitted_at' => now()->subDays(2),
                ]
            );

            ApprovalWorkflow::firstOrCreate(
                [
                    'approvable_type' => CurriculumChange::class,
                    'approvable_id' => $change->id,
                    'approver_role' => 'dean_academics',
                ],
                [
                    'status' => 'pending',
                    'sla_days' => 2,
                    'due_at' => now()->subDay(),
                ]
            );
        }

        if ($program && $subject && $semester) {
            $pendingExam = Exam::firstOrCreate(
                ['name' => 'Internal Assessment Pending Marks', 'subject_id' => $subject->id],
                [
                    'semester_id' => $semester->id,
                    'program_id' => $program->id,
                    'term_id' => $termId,
                    'type' => 'internal',
                    'exam_date' => now()->subDays(5)->toDateString(),
                    'total_marks' => 30,
                    'passing_marks' => 12,
                ]
            );

            $publishExam = Exam::firstOrCreate(
                ['name' => 'Midterm Publish Review', 'subject_id' => $subject->id],
                [
                    'semester_id' => $semester->id,
                    'program_id' => $program->id,
                    'term_id' => $termId,
                    'type' => 'midterm',
                    'exam_date' => now()->subDays(10)->toDateString(),
                    'total_marks' => 50,
                    'passing_marks' => 20,
                ]
            );

            if ($student) {
                $publishResult = ExamResult::firstOrCreate(
                    ['exam_id' => $publishExam->id, 'student_id' => $student->id],
                    ['marks_obtained' => 42, 'grade' => 'A', 'is_absent' => false]
                );

                MarksAppeal::firstOrCreate(
                    ['student_id' => $student->id, 'exam_result_id' => $publishResult->id],
                    [
                        'reason' => 'Rechecking requested',
                        'description' => 'Student has requested rechecking for one long-answer question.',
                        'marks_claimed' => 46,
                        'status' => 'pending',
                    ]
                );
            }

            $upcomingExam = Exam::firstOrCreate(
                ['name' => 'End Term Operations Demo', 'subject_id' => $subject->id],
                [
                    'semester_id' => $semester->id,
                    'program_id' => $program->id,
                    'term_id' => $termId,
                    'type' => 'final',
                    'exam_date' => now()->addDays(7)->toDateString(),
                    'total_marks' => 100,
                    'passing_marks' => 40,
                ]
            );

            if ($student) {
                ExamRegistration::firstOrCreate(
                    ['student_id' => $student->id, 'exam_id' => $upcomingExam->id],
                    [
                        'status' => 'pending',
                        'attendance_eligible' => false,
                        'fee_cleared' => false,
                        'remarks' => 'Attendance and fee clearance pending for hall ticket.',
                    ]
                );

                ExamAnomalyLog::firstOrCreate(
                    ['exam_id' => $publishExam->id, 'student_id' => $student->id, 'anomaly_type' => 'attendance_mismatch'],
                    [
                        'description' => 'Attendance sheet and invigilator record need reconciliation.',
                        'severity' => 'high',
                        'reported_by' => $coe->id,
                    ]
                );
            }

            DepartmentActivityLog::firstOrCreate(
                [
                    'department_id' => $department->id,
                    'action' => 'coe_marks_review_seeded',
                    'description' => 'Seeded CoE pending marks and result review examples.',
                ],
                [
                    'actor_user_id' => $coe->id,
                    'subject_type' => Exam::class,
                    'subject_id' => $pendingExam->id,
                    'metadata' => ['version' => 'Academics OS v0.011'],
                ]
            );
        }

        if ($student) {
            AcademicTranscript::firstOrCreate(
                ['student_id' => $student->id, 'academic_year' => '2024-25'],
                [
                    'cgpa' => 7.80,
                    'total_credits_earned' => 42,
                    'status' => 'draft',
                    'semester_data' => ['status' => 'pending_issue'],
                ]
            );
        }

        if ($student && $subject) {
            CourseFeedback::firstOrCreate(
                ['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $termId],
                [
                    'teaching_rating' => 4,
                    'content_rating' => 4,
                    'overall_rating' => 4,
                    'comments' => 'Good delivery with more case discussion requested.',
                    'is_anonymous' => true,
                ]
            );
        }

        app(AcademicPmcV003Service::class)->refreshCurriculumValidations($pmcHead);

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v0011_seeded',
                'description' => 'Seeded Academics OS v0.011 command center, workspace, and attention queue data.',
            ],
            [
                'actor_user_id' => $iqacHead->id,
                'metadata' => ['version' => 'Academics OS v0.011'],
            ]
        );

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v003_seeded',
                'description' => 'Seeded Academics OS v0.03 CoE operating data for exams, hall tickets, appeals, anomalies, and transcripts.',
            ],
            [
                'actor_user_id' => $coe->id,
                'metadata' => ['version' => 'Academics OS v0.03'],
            ]
        );

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v005_seeded',
                'description' => 'Seeded Academics OS v0.05 program leadership operating data across portfolio, delivery, student success, and quality signals.',
            ],
            [
                'actor_user_id' => $pmcHead->id,
                'metadata' => ['version' => 'Academics OS v0.05'],
            ]
        );

        app(AcademicsPmcOperatingDemoSeeder::class)->seedPmcOperatingSignals($department, $pmcHead, $program, $subject, $student, $semester, $termId);
        app(AcademicsPmcV003DemoSeeder::class)->seedPmcV003Signals($department, $pmcHead, $program, $subject, $student, $termId);
        app(AcademicsPmcV004DemoSeeder::class)->seedPmcV004Signals($department, $pmcHead, $program, $subject, $student, $termId);
        app(AcademicsPmcTimetableV041DemoSeeder::class)->seedPmcTimetableV041Signals($department, $dean, $pmcHead, $program, $subject, $student, $termId);
        app(AcademicsIqacOperatingDemoSeeder::class)->seedIqacOperatingSignals($department, $iqacHead, $program, $subject, $student, $termId);
        app(AcademicsCourseDeliveryDemoSeeder::class)->seedCourseDeliverySignals($department, $pmcHead, $program, $subject, $student, $semester, $termId);
        app(AcademicsDeanOperatingDemoSeeder::class)->seedDeanOperatingSignals($department, $dean, $pmcHead, $coe, $iqacHead, $program);
        app(AcademicsDeanV008DemoSeeder::class)->seedDeanV008Signals($department, $dean, $pmcHead, $coe, $iqacHead, $program, $subject, $student);
    }

}
