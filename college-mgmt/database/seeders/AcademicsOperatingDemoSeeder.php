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

class AcademicsOperatingDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'academic_department_owner',
            'dean_academics',
            'pmc_head',
            'pmc_manager',
            'pmc_officer',
            'coe',
            'exam_manager',
            'exam_officer',
            'iqac_head',
            'iqac_manager',
            'iqac_officer',
            'program_director',
            'program_leader',
            'semester_coordinator',
            'course_coordinator',
            'faculty_mentor',
            'program_chair',
            'hod',
            'exam_cell',
            'teacher',
            'faculty',
            'director',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $department = Department::firstOrCreate(
            ['code' => 'ACAD'],
            [
                'name' => 'Academic Operations',
                'description' => 'Academic planning, PMC, CoE, IQAC, program leadership, and student academic operations.',
                'head_name' => 'Dean Academics',
                'is_active' => true,
            ]
        );

        $branches = $this->branches($department);
        $roles = $this->roles($department);
        $admin = User::where('email', 'admin@demo.edu')->orWhere('email', 'admin@college.com')->first();
        $assignedBy = $admin?->id ?? User::query()->value('id') ?? 1;

        $owner = $this->user('director@college.com', 'Institute Director', ['director', 'academic_department_owner']);
        $dean = $this->user('dean@college.com', 'Dr. Meena Iyer', ['dean_academics']);
        $pmcHead = $this->user('chair@college.com', 'Prof. Anil Gupta', ['program_chair', 'pmc_head', 'program_director']);
        $pmcManager = $this->user('pmc.manager@college.com', 'Dr. Kavita Rao', ['pmc_manager', 'program_leader']);
        $pmcOfficer = $this->user('pmc.officer@college.com', 'Nisha Menon', ['pmc_officer']);
        $coe = $this->user('exam@college.com', 'Ritu Verma', ['exam_cell', 'coe']);
        $examManager = $this->user('exam.manager@college.com', 'Arvind Pillai', ['exam_manager']);
        $examOfficer = $this->user('exam.officer@college.com', 'Sameer Khan', ['exam_officer']);
        $iqacHead = $this->user('iqac.head@college.com', 'Dr. Farah Siddiqui', ['iqac_head']);
        $iqacManager = $this->user('iqac.manager@college.com', 'Manish Batra', ['iqac_manager']);
        $iqacOfficer = $this->user('iqac.officer@college.com', 'Asha Thomas', ['iqac_officer']);
        $programLeader = $this->user('hod@college.com', 'Dr. Suresh Nair', ['hod', 'program_leader']);
        $semesterCoordinator = $this->user('semester.coordinator@college.com', 'Megha Joshi', ['semester_coordinator']);
        $courseCoordinator = $this->user('course.coordinator@college.com', 'Rahul Sinha', ['course_coordinator']);
        $mentor = $this->user('faculty.mentor@college.com', 'Prof. Leena Das', ['faculty_mentor']);

        $ownerMember = $this->member($department, $owner, $roles['academic_department_owner'], $branches['dean_office']);
        $deanMember = $this->member($department, $dean, $roles['dean_academics'], $branches['dean_office'], $ownerMember);
        $pmcHeadMember = $this->member($department, $pmcHead, $roles['pmc_head'], $branches['pmc'], $deanMember);
        $pmcManagerMember = $this->member($department, $pmcManager, $roles['pmc_manager'], $branches['pmc'], $pmcHeadMember);
        $pmcOfficerMember = $this->member($department, $pmcOfficer, $roles['pmc_officer'], $branches['pmc'], $pmcManagerMember);
        $coeMember = $this->member($department, $coe, $roles['coe'], $branches['coe_examination'], $deanMember);
        $examManagerMember = $this->member($department, $examManager, $roles['exam_manager'], $branches['coe_examination'], $coeMember);
        $examOfficerMember = $this->member($department, $examOfficer, $roles['exam_officer'], $branches['coe_examination'], $examManagerMember);
        $iqacHeadMember = $this->member($department, $iqacHead, $roles['iqac_head'], $branches['iqac'], $deanMember);
        $iqacManagerMember = $this->member($department, $iqacManager, $roles['iqac_manager'], $branches['iqac'], $iqacHeadMember);
        $iqacOfficerMember = $this->member($department, $iqacOfficer, $roles['iqac_officer'], $branches['iqac'], $iqacManagerMember);
        $programLeaderMember = $this->member($department, $programLeader, $roles['program_leader'], $branches['program_leadership'], $pmcHeadMember);
        $semesterCoordinatorMember = $this->member($department, $semesterCoordinator, $roles['semester_coordinator'], $branches['program_leadership'], $programLeaderMember);
        $courseCoordinatorMember = $this->member($department, $courseCoordinator, $roles['course_coordinator'], $branches['program_leadership'], $semesterCoordinatorMember);
        $mentorMember = $this->member($department, $mentor, $roles['faculty_mentor'], $branches['program_leadership'], $semesterCoordinatorMember);

        $scopeService = app(AcademicScopeService::class);
        foreach ($branches as $type => $branch) {
            $this->scope($scopeService, $owner, $ownerMember, 'branch', $branch->id, $type, $branch->name, 'department_branch', true);
        }

        $this->scope($scopeService, $dean, $deanMember, 'branch', $branches['dean_office']->id, 'dean_office', 'Dean Office', 'department_branch', true);
        $this->scope($scopeService, $pmcHead, $pmcHeadMember, 'branch', $branches['pmc']->id, 'pmc', 'PMC', 'department_branch', true);
        $this->scope($scopeService, $coe, $coeMember, 'branch', $branches['coe_examination']->id, 'coe_examination', 'CoE / Examination', 'department_branch', true);
        $this->scope($scopeService, $iqacHead, $iqacHeadMember, 'branch', $branches['iqac']->id, 'iqac', 'IQAC', 'department_branch', true);
        $this->scope($scopeService, $programLeader, $programLeaderMember, 'branch', $branches['program_leadership']->id, 'program_leadership', 'Program Leadership', 'department_branch', true);

        $programs = Program::where('is_active', true)->orderBy('name')->get();
        foreach ($programs as $index => $program) {
            $programMember = $index === 0 ? $pmcHeadMember : $programLeaderMember;
            $programUser = $programMember->user;
            $this->scope($scopeService, $programUser, $programMember, 'program', $program->id, $program->code, $program->name, 'program_leadership', true);
            $this->scope($scopeService, $coe, $coeMember, 'program', $program->id, $program->code, $program->name, 'exam_program_operations', true);
            $this->scope($scopeService, $examManager, $examManagerMember, 'program', $program->id, $program->code, $program->name, 'exam_program_operations', true);
            $this->scope($scopeService, $examOfficer, $examOfficerMember, 'program', $program->id, $program->code, $program->name, 'exam_program_operations', false);
            $this->scope($scopeService, $iqacHead, $iqacHeadMember, 'program', $program->id, $program->code, $program->name, 'quality_audit', true);
            $this->scope($scopeService, $iqacManager, $iqacManagerMember, 'program', $program->id, $program->code, $program->name, 'quality_audit', true);
            $this->scope($scopeService, $iqacOfficer, $iqacOfficerMember, 'program', $program->id, $program->code, $program->name, 'quality_audit', false);
            RoleProgramAssignment::firstOrCreate(
                ['user_id' => $programUser->id, 'role_name' => $programUser->hasRole('program_chair') ? 'program_chair' : 'hod', 'program_id' => $program->id],
                ['batch_id' => null, 'is_active' => true, 'assigned_by' => $assignedBy, 'assigned_at' => now()]
            );
        }

        $firstProgram = $programs->first();
        if ($firstProgram) {
            Batch::where('program_id', $firstProgram->id)->limit(3)->get()->each(function (Batch $batch) use ($scopeService, $pmcManager, $pmcManagerMember, $examManager, $examManagerMember) {
                $this->scope($scopeService, $pmcManager, $pmcManagerMember, 'batch', $batch->id, $batch->code, $batch->name, 'pmc_batch_operations', true);
                $this->scope($scopeService, $examManager, $examManagerMember, 'batch', $batch->id, $batch->code, $batch->name, 'exam_batch_operations', true);
            });

            Term::where('program_id', $firstProgram->id)->orWhereHas('batch', fn ($query) => $query->where('program_id', $firstProgram->id))
                ->limit(4)
                ->get()
                ->each(fn (Term $term) => $this->scope($scopeService, $semesterCoordinator, $semesterCoordinatorMember, 'term', $term->id, 'TERM-' . $term->id, $term->name, 'term_coordination', true));

            Subject::where('program_id', $firstProgram->id)->limit(5)->get()
                ->each(fn (Subject $subject) => $this->scope($scopeService, $courseCoordinator, $courseCoordinatorMember, 'subject', $subject->id, $subject->code, $subject->name, 'course_coordination', true));
        }

        $this->scope($scopeService, $pmcOfficer, $pmcOfficerMember, 'cohort', null, 'PGDM-ACTIVE', 'Active PGDM Student Cohort', 'student_support', false);
        $this->scope($scopeService, $examOfficer, $examOfficerMember, 'cohort', null, 'EXAM-DUE', 'Exam Registration Cohort', 'exam_operations', false);
        $this->scope($scopeService, $iqacManager, $iqacManagerMember, 'program', $firstProgram?->id, $firstProgram?->code, $firstProgram?->name ?? 'All Active Programs', 'quality_audit', true);
        $this->scope($scopeService, $iqacOfficer, $iqacOfficerMember, 'cohort', null, 'OBE-EVIDENCE', 'OBE Evidence Cohort', 'quality_audit', false);
        $this->scope($scopeService, $mentor, $mentorMember, 'cohort', null, 'MENTOR-GROUP-A', 'Mentor Group A', 'student_mentoring', false);

        DepartmentActivityLog::firstOrCreate(
            [
                'department_id' => $department->id,
                'action' => 'academics_os_seeded',
                'description' => 'Seeded Academics OS v0.01 hierarchy, branch reporting, and academic scopes.',
            ],
            [
                'actor_user_id' => $admin?->id,
                'metadata' => ['version' => 'Academics OS v0.01'],
            ]
        );

        app(AcademicsOperationalSignalsDemoSeeder::class)->seedOperationalSignals($department, $dean, $pmcHead, $coe, $iqacHead);
    }


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

    private function branches(Department $department): array
    {
        $branches = [];
        foreach ([
            'dean_office' => 'Dean Office',
            'pmc' => 'PMC',
            'coe_examination' => 'CoE / Examination',
            'iqac' => 'IQAC',
            'program_leadership' => 'Program Leadership',
        ] as $type => $name) {
            $branches[$type] = DepartmentTeam::firstOrCreate(
                ['department_id' => $department->id, 'name' => $name],
                ['type' => $type, 'scope_rules' => ['branch' => $type], 'is_active' => true]
            );
        }

        return $branches;
    }

    private function roles(Department $department): array
    {
        return DepartmentRole::where('department_id', $department->id)
            ->whereIn('code', [
                'academic_department_owner',
                'dean_academics',
                'pmc_head',
                'pmc_manager',
                'pmc_officer',
                'coe',
                'exam_manager',
                'exam_officer',
                'iqac_head',
                'iqac_manager',
                'iqac_officer',
                'program_leader',
                'semester_coordinator',
                'course_coordinator',
                'faculty_mentor',
            ])
            ->get()
            ->keyBy('code')
            ->all();
    }

    private function member(Department $department, User $user, DepartmentRole $role, DepartmentTeam $team, ?DepartmentMember $manager = null): DepartmentMember
    {
        return DepartmentMember::updateOrCreate(
            [
                'department_id' => $department->id,
                'user_id' => $user->id,
                'department_role_id' => $role->id,
            ],
            [
                'department_team_id' => $team->id,
                'reports_to_member_id' => $manager?->id,
                'is_active' => true,
            ]
        )->load(['user', 'role', 'team']);
    }

    private function scope(AcademicScopeService $service, User $actor, DepartmentMember $member, string $type, ?int $id, ?string $code, string $name, string $context, bool $canManage): void
    {
        $service->assign($actor, $member->load('user'), $type, $id, $code, $name, $context, $canManage);
    }
}
