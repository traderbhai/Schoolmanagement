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

class AcademicsPmcOperatingDemoSeeder extends Seeder
{
    public function seedPmcOperatingSignals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?Semester $semester, ?int $termId): void
    {
        if (! $program || ! $subject || ! $semester) {
            return;
        }

        if (! $termId) {
            $batch = Batch::firstOrCreate(
                ['code' => ($program->code ?: 'ACAD') . '-PMC-DEMO'],
                [
                    'program_id' => $program->id,
                    'name' => ($program->code ?: $program->name) . ' PMC Demo Batch',
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

        $facultyUser = $this->user('pmc.faculty@college.com', 'Prof. Aditi Sen', []);
        $teacher = Teacher::firstOrCreate(
            ['user_id' => $facultyUser->id],
            [
                'department_id' => $program->department_id ?? $department->id,
                'employee_id' => 'PMC-FAC-001',
                'designation' => 'Assistant Professor',
                'qualification' => 'PhD',
                'specialization' => 'Analytics And Strategy',
                'status' => 'active',
            ]
        );

        ProgramSubject::firstOrCreate(
            ['program_id' => $program->id, 'subject_id' => $subject->id],
            ['term_id' => $termId, 'type' => 'compulsory', 'credits' => $subject->credits ?? 3, 'is_active' => true]
        );

        $allocationSubject = Subject::firstOrCreate(
            ['code' => 'PMC-ALLOC-101'],
            [
                'department_id' => $program->department_id ?? $department->id,
                'program_id' => $program->id,
                'term_number' => 1,
                'name' => 'PMC Faculty Allocation Demo',
                'credits' => 3,
                'type' => 'theory',
                'hours_per_week' => 3,
                'is_active' => true,
            ]
        );

        ProgramSubject::firstOrCreate(
            ['program_id' => $program->id, 'subject_id' => $allocationSubject->id],
            ['term_id' => $termId, 'type' => 'compulsory', 'credits' => 3, 'is_active' => true]
        );

        SubjectFacultyAssignment::firstOrCreate(
            ['subject_id' => $allocationSubject->id, 'teacher_id' => $teacher->id, 'program_id' => $program->id],
            ['term_id' => $termId, 'assigned_by' => $pmcHead->id, 'is_primary' => true]
        );

        $mappingGap = Subject::firstOrCreate(
            ['code' => 'PMC-GAP-101'],
            [
                'department_id' => $program->department_id ?? $department->id,
                'program_id' => $program->id,
                'term_number' => 1,
                'name' => 'Industry Immersion Lab',
                'description' => 'Pending curriculum mapping for PMC readiness demo.',
                'credits' => 2,
                'type' => 'practical',
                'hours_per_week' => 2,
                'is_active' => true,
            ]
        );

        foreach ([['PMC-WL-201', 'Advanced Analytics Studio'], ['PMC-WL-202', 'Decision Lab']] as [$code, $name]) {
            $workloadSubject = Subject::firstOrCreate(
                ['code' => $code],
                [
                    'department_id' => $program->department_id ?? $department->id,
                    'program_id' => $program->id,
                    'term_number' => 1,
                    'name' => $name,
                    'credits' => 3,
                    'type' => 'theory',
                    'hours_per_week' => 3,
                    'is_active' => true,
                ]
            );

            ProgramSubject::firstOrCreate(
                ['program_id' => $program->id, 'subject_id' => $workloadSubject->id],
                ['term_id' => $termId, 'type' => 'compulsory', 'credits' => 3, 'is_active' => true]
            );

            SubjectFacultyAssignment::firstOrCreate(
                ['subject_id' => $workloadSubject->id, 'teacher_id' => $teacher->id, 'program_id' => $program->id],
                ['term_id' => $termId, 'assigned_by' => $pmcHead->id, 'is_primary' => false]
            );
        }

        $slot = TimetableSlot::firstOrCreate(
            ['name' => 'PMC Demo Period 1'],
            ['start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 1, 'is_active' => true]
        );
        $classroom = Classroom::firstOrCreate(
            ['room_number' => 'PMC-101'],
            ['name' => 'PMC Smart Classroom', 'capacity' => 60, 'type' => 'lecture', 'building' => 'Academic Block', 'is_active' => true]
        );
        $course = Course::firstOrCreate(
            ['code' => ($program->code ?: 'ACAD') . '-PMC'],
            [
                'department_id' => $program->department_id ?? $department->id,
                'name' => ($program->name ?: 'Academic') . ' PMC Course',
                'duration_years' => $program->duration_years ?? 2,
                'total_semesters' => $program->total_terms ?? 4,
                'is_active' => true,
            ]
        );
        $draftEntry = TimetableEntry::firstOrCreate(
            [
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'subject_id' => $mappingGap->id,
                'teacher_id' => $teacher->id,
                'timetable_slot_id' => $slot->id,
                'day_of_week' => 1,
            ],
            [
                'term_id' => $termId,
                'classroom_id' => $classroom->id,
                'is_active' => true,
                'status' => 'draft',
            ]
        );

        if ($student) {
            foreach ([now()->subDays(3), now()->subDays(2)] as $date) {
                $existingAttendance = Attendance::where('student_id', $student->id)
                    ->where('timetable_entry_id', $draftEntry->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if (! $existingAttendance) {
                    Attendance::create([
                        'student_id' => $student->id,
                        'timetable_entry_id' => $draftEntry->id,
                        'date' => $date->toDateString(),
                        'status' => 'absent',
                        'remarks' => 'PMC demo risk signal',
                        'marked_by' => $pmcHead->id,
                    ]);
                }
            }

            $riskExam = Exam::firstOrCreate(
                ['name' => 'PMC Weak Performance Review', 'subject_id' => $subject->id],
                [
                    'semester_id' => $semester->id,
                    'program_id' => $program->id,
                    'term_id' => $termId,
                    'type' => 'internal',
                    'exam_date' => now()->subDays(4)->toDateString(),
                    'total_marks' => 30,
                    'passing_marks' => 12,
                ]
            );

            ExamResult::firstOrCreate(
                ['exam_id' => $riskExam->id, 'student_id' => $student->id],
                ['marks_obtained' => 8, 'grade' => 'F', 'is_absent' => false, 'remarks' => 'PMC intervention required']
            );

            LeaveApplication::firstOrCreate(
                ['student_id' => $student->id, 'from_date' => now()->addDay()->toDateString(), 'leave_type' => 'medical'],
                [
                    'teacher_id' => $teacher->id,
                    'to_date' => now()->addDays(2)->toDateString(),
                    'days' => 2,
                    'reason' => 'Medical leave pending PMC review',
                    'status' => 'pending',
                ]
            );
        }

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v002_seeded',
                'description' => 'Seeded Academics OS v0.02 PMC operating data for curriculum, faculty, timetable, and student monitoring.',
            ],
            [
                'actor_user_id' => $pmcHead->id,
                'metadata' => ['version' => 'Academics OS v0.02'],
            ]
        );
    }
}
