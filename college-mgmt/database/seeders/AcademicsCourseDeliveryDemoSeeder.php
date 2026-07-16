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

class AcademicsCourseDeliveryDemoSeeder extends Seeder
{
    private function user(string $email, string $name, array $roles): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')]
        );

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function seedCourseDeliverySignals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?Semester $semester, ?int $termId): void
    {
        if (! $program || ! $subject || ! $semester) {
            return;
        }

        if (! $termId) {
            $batch = Batch::firstOrCreate(
                ['code' => ($program->code ?: 'ACAD') . '-CD-DEMO'],
                [
                    'program_id' => $program->id,
                    'name' => ($program->code ?: $program->name) . ' Course Delivery Demo Batch',
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

        $facultyUser = $this->user('pmc.faculty@college.com', 'Prof. Aditi Sen', ['teacher', 'faculty']);
        $mentorUser = User::where('email', 'faculty.mentor@college.com')->first() ?: $facultyUser;
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

        SubjectFacultyAssignment::firstOrCreate(
            ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'program_id' => $program->id],
            ['term_id' => $termId, 'assigned_by' => $pmcHead->id, 'is_primary' => true]
        );

        $deliverySubject = Subject::firstOrCreate(
            ['code' => 'CD-OPS-101'],
            [
                'department_id' => $program->department_id ?? $department->id,
                'program_id' => $program->id,
                'term_number' => 1,
                'name' => 'Course Delivery Operations Lab',
                'description' => 'Seeded v0.06 subject for faculty operating desk.',
                'credits' => 2,
                'type' => 'practical',
                'hours_per_week' => 2,
                'is_active' => true,
            ]
        );

        ProgramSubject::firstOrCreate(
            ['program_id' => $program->id, 'subject_id' => $deliverySubject->id],
            ['term_id' => $termId, 'type' => 'compulsory', 'credits' => 2, 'is_active' => true]
        );

        CourseOutcome::firstOrCreate(
            ['subject_id' => $deliverySubject->id, 'code' => 'CO-CD-1'],
            ['description' => 'Demonstrate course-delivery planning and intervention tracking.', 'bloom_level' => 'apply', 'is_active' => true]
        );

        SubjectFacultyAssignment::firstOrCreate(
            ['subject_id' => $deliverySubject->id, 'teacher_id' => $teacher->id, 'program_id' => $program->id],
            ['term_id' => $termId, 'assigned_by' => $pmcHead->id, 'is_primary' => false]
        );

        $slot = TimetableSlot::firstOrCreate(
            ['name' => 'Course Delivery Demo Period'],
            ['start_time' => '11:00', 'end_time' => '12:00', 'is_break' => false, 'sort_order' => 3, 'is_active' => true]
        );
        $classroom = Classroom::firstOrCreate(
            ['room_number' => 'CD-201'],
            ['name' => 'Course Delivery Studio', 'capacity' => 45, 'type' => 'lab', 'building' => 'Academic Block', 'is_active' => true]
        );
        $course = Course::firstOrCreate(
            ['code' => ($program->code ?: 'ACAD') . '-CD'],
            [
                'department_id' => $program->department_id ?? $department->id,
                'name' => ($program->name ?: 'Academic') . ' Course Delivery',
                'duration_years' => $program->duration_years ?? 2,
                'total_semesters' => $program->total_terms ?? 4,
                'is_active' => true,
            ]
        );
        $entry = TimetableEntry::firstOrCreate(
            [
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'subject_id' => $deliverySubject->id,
                'teacher_id' => $teacher->id,
                'timetable_slot_id' => $slot->id,
                'day_of_week' => now()->dayOfWeekIso,
            ],
            [
                'term_id' => $termId,
                'classroom_id' => $classroom->id,
                'is_active' => true,
                'status' => 'published',
            ]
        );

        if ($student) {
            $student->update(['mentor_id' => $mentorUser->id]);

            StudentSubjectEnrollment::firstOrCreate(
                ['student_id' => $student->id, 'subject_id' => $deliverySubject->id, 'term_id' => $termId],
                ['enrollment_type' => 'compulsory', 'status' => 'active']
            );

            foreach ([now()->subDays(2), now()->subDay()] as $date) {
                $existingAttendance = Attendance::where('student_id', $student->id)
                    ->where('timetable_entry_id', $entry->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if (! $existingAttendance) {
                    Attendance::create([
                        'student_id' => $student->id,
                        'timetable_entry_id' => $entry->id,
                        'date' => $date->toDateString(),
                        'status' => 'late',
                        'remarks' => 'v0.06 course delivery follow-up signal',
                        'marked_by' => $facultyUser->id,
                    ]);
                }
            }

            CourseFeedback::firstOrCreate(
                ['student_id' => $student->id, 'subject_id' => $deliverySubject->id, 'term_id' => $termId],
                [
                    'teaching_rating' => 3,
                    'content_rating' => 3,
                    'overall_rating' => 3,
                    'comments' => 'Need clearer recap notes and more applied examples.',
                    'is_anonymous' => true,
                ]
            );

            MentorMeeting::firstOrCreate(
                ['student_id' => $student->id, 'mentor_id' => $mentorUser->id, 'meeting_date' => now()->addDays(2)->toDateString()],
                ['topic' => 'Attendance recovery plan', 'notes' => 'Course Delivery OS demo mentor follow-up.', 'status' => 'scheduled']
            );

            MentorMessage::firstOrCreate(
                ['student_id' => $student->id, 'sender_id' => $mentorUser->id, 'message' => 'Please meet after class to discuss attendance recovery and course support.'],
                ['read_at' => null]
            );
        }

        SubjectAnnouncement::firstOrCreate(
            ['subject_id' => $deliverySubject->id, 'posted_by' => $facultyUser->id, 'title' => 'Course Delivery Lab Prep'],
            ['term_id' => $termId, 'body' => 'Bring the case worksheet and review the previous session summary.', 'is_pinned' => true]
        );

        SubjectDiscussion::firstOrCreate(
            ['subject_id' => $deliverySubject->id, 'posted_by' => $facultyUser->id, 'title' => 'Clarify attendance recovery task'],
            ['term_id' => $termId, 'body' => 'Students have asked for clarification on the recovery activity.', 'is_pinned' => false, 'is_resolved' => false, 'views' => 12]
        );

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_v006_seeded',
                'description' => 'Seeded Academics OS v0.06 course delivery data for faculty load, sessions, attendance, engagement, and mentoring.',
            ],
            [
                'actor_user_id' => $pmcHead->id,
                'metadata' => ['version' => 'Academics OS v0.06'],
            ]
        );
    }

}
