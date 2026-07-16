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

        $this->seedOperationalSignals($department, $dean, $pmcHead, $coe, $iqacHead);
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

    private function seedOperationalSignals(Department $department, User $dean, User $pmcHead, User $coe, User $iqacHead): void
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
        $this->seedPmcV003Signals($department, $pmcHead, $program, $subject, $student, $termId);
        app(AcademicsPmcV004DemoSeeder::class)->seedPmcV004Signals($department, $pmcHead, $program, $subject, $student, $termId);
        $this->seedPmcTimetableV041Signals($department, $dean, $pmcHead, $program, $subject, $student, $termId);
        app(AcademicsIqacOperatingDemoSeeder::class)->seedIqacOperatingSignals($department, $iqacHead, $program, $subject, $student, $termId);
        app(AcademicsCourseDeliveryDemoSeeder::class)->seedCourseDeliverySignals($department, $pmcHead, $program, $subject, $student, $semester, $termId);
        $this->seedDeanOperatingSignals($department, $dean, $pmcHead, $coe, $iqacHead, $program);
        app(AcademicsDeanV008DemoSeeder::class)->seedDeanV008Signals($department, $dean, $pmcHead, $coe, $iqacHead, $program, $subject, $student);
    }

    private function seedDeanOperatingSignals(Department $department, User $dean, User $pmcHead, User $coe, User $iqacHead, ?Program $program): void
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

    private function seedPmcV003Signals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?int $termId): void
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

    private function seedPmcTimetableV041Signals(Department $department, User $dean, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?int $termId): void
    {
        if (! $program || ! $subject) {
            return;
        }

        $batch = Batch::where('program_id', $program->id)->first();
        $term = $termId ? Term::find($termId) : Term::where('program_id', $program->id)->orWhere('batch_id', $batch?->id)->first();
        $pmcManager = User::where('email', 'pmc.manager@college.com')->first() ?: $pmcHead;
        $pmcOfficer = User::where('email', 'pmc.officer@college.com')->first() ?: $pmcHead;
        $facultyUser = $this->user('pmc.faculty@college.com', 'Prof. Aditi Sen', []);
        $adjunctUser = $this->user('pmc.adjunct@college.com', 'Prof. Vikram Shah', ['teacher']);
        $teacher = Teacher::firstOrCreate(
            ['user_id' => $facultyUser->id],
            ['department_id' => $program->department_id ?? $department->id, 'employee_id' => 'PMC-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Analytics And Strategy', 'status' => 'active']
        );
        $adjunct = Teacher::firstOrCreate(
            ['user_id' => $adjunctUser->id],
            ['department_id' => $program->department_id ?? $department->id, 'employee_id' => 'PMC-ADJ-001', 'designation' => 'Visiting Faculty', 'qualification' => 'MBA', 'specialization' => 'Digital Marketing', 'employment_type' => 'visiting', 'status' => 'active']
        );
        $slotOne = TimetableSlot::updateOrCreate(
            ['name' => 'PMC v041 Period 1'],
            ['start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 1, 'is_active' => true]
        );
        TimetableSlot::updateOrCreate(
            ['name' => 'PMC v062 Lunch Break'],
            ['start_time' => '10:00', 'end_time' => '10:30', 'is_break' => true, 'sort_order' => 2, 'is_active' => true]
        );
        $slotTwo = TimetableSlot::updateOrCreate(
            ['name' => 'PMC v041 Period 2'],
            ['start_time' => '10:30', 'end_time' => '11:30', 'is_break' => false, 'sort_order' => 3, 'is_active' => true]
        );
        TimetableSlot::updateOrCreate(
            ['name' => 'PMC v062 Period 3'],
            ['start_time' => '11:30', 'end_time' => '12:30', 'is_break' => false, 'sort_order' => 4, 'is_active' => true]
        );
        $room = Classroom::firstOrCreate(
            ['room_number' => 'PMC-V041-101'],
            ['name' => 'PMC v041 Lecture Room', 'capacity' => 70, 'type' => 'lecture', 'building' => 'Academic Block', 'is_active' => true]
        );
        $parallelRoom = Classroom::firstOrCreate(
            ['room_number' => 'PMC-V041-102'],
            ['name' => 'PMC v041 Parallel Lecture Room', 'capacity' => 70, 'type' => 'lecture', 'building' => 'Academic Block', 'is_active' => true]
        );
        $lab = Classroom::firstOrCreate(
            ['room_number' => 'PMC-V041-LAB'],
            ['name' => 'PMC v041 Analytics Lab', 'capacity' => 35, 'type' => 'lab', 'building' => 'Academic Block', 'has_lab' => true, 'is_active' => true]
        );
        $elective = Subject::firstOrCreate(
            ['code' => 'PMC-ELEC-401'],
            ['department_id' => $program->department_id ?? $department->id, 'program_id' => $program->id, 'term_number' => 1, 'name' => 'Open Elective: Growth Analytics', 'credits' => 3, 'type' => 'theory', 'hours_per_week' => 3, 'is_active' => true]
        );
        $labSubject = Subject::firstOrCreate(
            ['code' => 'PMC-LAB-401'],
            ['department_id' => $program->department_id ?? $department->id, 'program_id' => $program->id, 'term_number' => 1, 'name' => 'Decision Analytics Lab', 'credits' => 2, 'type' => 'practical', 'hours_per_week' => 2, 'is_active' => true]
        );

        foreach ([$subject, $elective, $labSubject] as $mappedSubject) {
            $programSubject = ProgramSubject::firstOrCreate(
                ['program_id' => $program->id, 'subject_id' => $mappedSubject->id],
                ['term_id' => $term?->id, 'type' => $mappedSubject->id === $elective->id ? 'elective' : 'compulsory', 'credits' => $mappedSubject->credits ?? 3, 'is_active' => true]
            );

            if ($term) {
                foreach ([['IA1', 20, 20], ['IA2', 20, 20], ['End-Sem', 100, 60]] as [$componentName, $maxMarks, $weightage]) {
                    PmcAssessmentComponentConfig::updateOrCreate(
                        ['subject_id' => $mappedSubject->id, 'term_id' => $term->id, 'name' => $componentName],
                        [
                            'program_subject_id' => $programSubject->id,
                            'program_id' => $program->id,
                            'max_marks' => $maxMarks,
                            'weightage' => $weightage,
                            'created_by' => $pmcHead->id,
                        ]
                    );
                }
            }
        }
        $pmcPo = ProgramOutcome::firstOrCreate(
            ['program_id' => $program->id, 'code' => 'PO-PMC-V041'],
            ['description' => 'Apply academic planning and timetable operations in realistic institute contexts.', 'category' => 'management', 'is_active' => true]
        );
        foreach ([[$elective, 'CO-PMC-ELEC-1'], [$labSubject, 'CO-PMC-LAB-1']] as [$outcomeSubject, $code]) {
            $pmcCo = CourseOutcome::firstOrCreate(
                ['subject_id' => $outcomeSubject->id, 'code' => $code],
                ['description' => 'Seeded v0.041 timetable-linked course outcome.', 'bloom_level' => 'apply', 'is_active' => true]
            );
            CoPoMapping::firstOrCreate(
                ['course_outcome_id' => $pmcCo->id, 'program_outcome_id' => $pmcPo->id],
                ['correlation_level' => 2]
            );
        }

        $allocationBatch = AcademicPmcCourseAllocationBatch::firstOrCreate(
            ['title' => 'PMC v0.041 Term Course Allocation', 'program_id' => $program->id],
            ['batch_id' => $batch?->id, 'term_id' => $term?->id, 'owner_user_id' => $pmcHead->id, 'status' => 'approved', 'student_count' => 1, 'core_allocations' => 2, 'elective_allocations' => 1, 'conflict_count' => 1, 'rules' => ['max_credits' => 28, 'elective_priority' => true]]
        );

        if ($student) {
            foreach ([[$subject, 'core', false, null], [$elective, 'elective', true, 'Capacity full; waitlisted for second choice'], [$labSubject, 'core', false, 'Dean-approved lab overload for demo']] as [$allocatedSubject, $type, $waitlisted, $reason]) {
                $enrollment = StudentSubjectEnrollment::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $allocatedSubject->id, 'term_id' => $term?->id],
                    ['enrollment_type' => $type === 'elective' ? 'elective' : 'compulsory', 'status' => 'active']
                );
                AcademicPmcStudentCourseAllocation::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $allocatedSubject->id, 'term_id' => $term?->id],
                    ['allocation_batch_id' => $allocationBatch->id, 'student_subject_enrollment_id' => $enrollment->id, 'allocation_type' => $type, 'allocation_source' => $type === 'elective' ? 'choice_window' : 'bulk_core', 'approval_status' => $reason ? 'override_approved' : 'allocated', 'basket_status' => 'approved', 'waitlisted' => $waitlisted, 'override_reason' => $reason, 'validation_flags' => $waitlisted ? ['waitlist' => true] : []]
                );
            }
            $coreAllocation = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('subject_id', $subject->id)->where('term_id', $term?->id)->first();
            foreach ([[$coreAllocation, $subject, 'drop', 'returned', false, 'Student requested drop but core subject is mandatory.', ['mandatory_core_drop'], 'Core subject cannot be dropped without Dean exception.'], [null, $labSubject, 'improvement', 'requested', true, 'Student wants improvement attempt with extra lab credit.', ['dean_review_required'], null], [null, $elective, 'open_elective', 'approved', false, 'Open elective approved after capacity review.', [], 'Approved within elective capacity.']] as [$allocation, $exceptionSubject, $type, $status, $deanRequired, $reason, $flags, $note]) {
                AcademicPmcCourseAllocationException::updateOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $exceptionSubject->id, 'term_id' => $term?->id, 'exception_type' => $type],
                    ['student_course_allocation_id' => $allocation?->id, 'status' => $status, 'credit_delta' => $exceptionSubject->credits ?? 3, 'requires_dean_approval' => $deanRequired, 'reason' => $reason, 'validation_flags' => $flags, 'requested_by' => $pmcOfficer->id, 'requested_at' => now()->subDays(2), 'decided_by' => $note ? $pmcHead->id : null, 'decided_at' => $note ? now()->subDay() : null, 'decision_note' => $note, 'metadata' => ['version' => 'PMC OS v0.050']]
                );
            }
        }

        $coreGroup = AcademicPmcCourseGroup::updateOrCreate(
            ['name' => 'PGDM Core Section A', 'program_id' => $program->id, 'batch_id' => $batch?->id, 'term_id' => $term?->id],
            ['subject_id' => $subject->id, 'group_type' => 'core_section', 'owner_user_id' => $pmcManager->id, 'min_capacity' => 20, 'max_capacity' => 60, 'current_strength' => $student ? 1 : 0, 'status' => 'locked', 'is_locked' => true, 'constraints' => ['compact_student_day' => true]]
        );
        $electiveGroup = AcademicPmcCourseGroup::updateOrCreate(
            ['name' => 'Growth Analytics Elective Group 1', 'program_id' => $program->id, 'batch_id' => $batch?->id, 'term_id' => $term?->id],
            ['subject_id' => $elective->id, 'group_type' => 'elective_group', 'owner_user_id' => $pmcOfficer->id, 'min_capacity' => 10, 'max_capacity' => 35, 'current_strength' => $student ? 1 : 0, 'status' => 'conflict_review', 'is_locked' => false, 'constraints' => ['elective_min_strength' => 10]]
        );
        $labGroup = AcademicPmcCourseGroup::updateOrCreate(
            ['name' => 'Decision Analytics Lab Group L1', 'program_id' => $program->id, 'batch_id' => $batch?->id, 'term_id' => $term?->id],
            ['subject_id' => $labSubject->id, 'group_type' => 'lab_group', 'owner_user_id' => $pmcOfficer->id, 'min_capacity' => 8, 'max_capacity' => 30, 'current_strength' => $student ? 1 : 0, 'status' => 'ready', 'is_locked' => false, 'constraints' => ['double_slot_required' => true]]
        );

        if ($student) {
            foreach ([$coreGroup, $electiveGroup, $labGroup] as $group) {
                $allocationId = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('subject_id', $group->subject_id)->value('id');
                AcademicPmcCourseGroupMember::firstOrCreate(
                    ['course_group_id' => $group->id, 'student_id' => $student->id],
                    ['student_course_allocation_id' => $allocationId, 'status' => 'active', 'moved_by' => $pmcHead->id, 'move_reason' => 'Seeded v0.041 group membership']
                );
            }

            $electiveAllocation = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('subject_id', $elective->id)->where('term_id', $term?->id)->first();
            $labAllocation = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('subject_id', $labSubject->id)->where('term_id', $term?->id)->first();
            foreach ([
                [$coreAllocation, 'allocation_review', 'acknowledged', 'Core basket reviewed and accepted.', 'Reviewed by PMC during launch readiness.'],
                [$electiveAllocation, 'waitlist_followup', 'under_review', 'Please confirm my waitlist movement possibility for Growth Analytics.', null],
                [$labAllocation, 'objection', 'objection_submitted', 'The assigned lab section conflicts with my approved mentoring slot.', null],
            ] as [$ackAllocation, $type, $status, $studentNote, $pmcNote]) {
                AcademicPmcStudentBasketAcknowledgement::updateOrCreate(
                    ['student_id' => $student->id, 'student_course_allocation_id' => $ackAllocation?->id, 'acknowledgement_type' => $type],
                    [
                        'status' => $status,
                        'reason' => $type === 'objection' ? 'Potential schedule clash' : null,
                        'student_note' => $studentNote,
                        'pmc_note' => $pmcNote,
                        'submitted_at' => now()->subDays($type === 'allocation_review' ? 3 : 1),
                        'decided_by' => $pmcNote ? $pmcOfficer->id : null,
                        'decided_at' => $pmcNote ? now()->subDays(2) : null,
                        'metadata' => ['version' => 'PMC OS v0.090', 'source' => 'demo_seed'],
                    ]
                );
            }
        }
        foreach ([[$coreGroup, $electiveGroup, null, 'rebalance', 'approved', false, 1, 'Rebalanced one student from core overflow into elective support group.'], [$electiveGroup, null, null, 'lock', 'approved', false, 0, 'Locked elective group after add/drop window.'], [$labGroup, $coreGroup, $student?->id, 'move_student', 'requested', true, 1, 'Move needs Dean review because lab cohort is already under minimum.']] as [$sourceGroup, $targetGroup, $adjustStudentId, $type, $status, $deanRequired, $delta, $note]) {
            AcademicPmcCourseGroupAdjustment::updateOrCreate(
                ['course_group_id' => $sourceGroup->id, 'target_course_group_id' => $targetGroup?->id, 'adjustment_type' => $type],
                ['student_id' => $adjustStudentId, 'status' => $status, 'from_strength' => $sourceGroup->current_strength, 'to_strength' => max(0, $sourceGroup->current_strength - $delta), 'target_from_strength' => $targetGroup?->current_strength ?? 0, 'target_to_strength' => $targetGroup ? $targetGroup->current_strength + $delta : 0, 'requires_dean_approval' => $deanRequired, 'reason' => $note, 'impact_summary' => ['source' => $sourceGroup->name, 'target' => $targetGroup?->name, 'strength_delta' => $delta], 'requested_by' => $pmcOfficer->id, 'requested_at' => now()->subDays(2), 'decided_by' => $status === 'approved' ? $pmcHead->id : null, 'decided_at' => $status === 'approved' ? now()->subDay() : null, 'decision_note' => $status === 'approved' ? $note : null, 'metadata' => ['version' => 'PMC OS v0.051']]
            );
        }

        foreach ([[$coreGroup, $teacher, 'primary', 3, false], [$electiveGroup, $adjunct, 'primary', 3, false], [$labGroup, $teacher, 'lab_faculty', 2, false], [$coreGroup, $adjunct, 'backup', 0, true]] as [$group, $assignedTeacher, $role, $hours, $backup]) {
            $assignment = AcademicPmcGroupFacultyAssignment::updateOrCreate(
                ['course_group_id' => $group->id, 'teacher_id' => $assignedTeacher->id, 'assignment_role' => $role],
                ['assignment_source' => $role === 'backup' ? 'area_chair_recommended' : 'pmc', 'approval_status' => 'pmc_approved', 'weekly_hours' => $hours, 'is_backup' => $backup, 'assigned_by' => $pmcHead->id, 'notes' => 'Seeded v0.041 exact group-level assignment']
            );
            AcademicPmcFacultyAssignmentAcknowledgement::updateOrCreate(
                ['group_faculty_assignment_id' => $assignment->id, 'teacher_id' => $assignedTeacher->id],
                ['status' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? 'concern_raised' : 'accepted', 'response_type' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? 'accept_with_constraints' : 'accept', 'faculty_note' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? 'Can teach only Tuesday and Thursday.' : 'Faculty acknowledged assigned group.', 'constraints_raised' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? ['adjunct_day_limit'] : [], 'requested_by' => $pmcHead->id, 'requested_at' => now()->subDays(3), 'responded_by' => $assignedTeacher->user_id, 'responded_at' => now()->subDays(2), 'reviewed_by' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? null : $pmcHead->id, 'reviewed_at' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? null : now()->subDay(), 'review_note' => $assignedTeacher->id === $adjunct->id && $role === 'primary' ? null : 'Acknowledgement reviewed.', 'metadata' => ['version' => 'PMC OS v0.052']]
            );
        }

        AcademicPmcFacultyPreference::updateOrCreate(
            ['teacher_id' => $adjunct->id, 'term_id' => $term?->id],
            ['faculty_type' => 'adjunct', 'available_days' => [2, 4], 'preferred_slots' => [$slotOne->id], 'unavailable_slots' => [1 => [$slotTwo->id], 3 => [$slotOne->id, $slotTwo->id]], 'max_classes_per_day' => 2, 'max_consecutive_classes' => 2, 'max_weekly_load' => 8, 'subject_expertise' => [$elective->code], 'restriction_notes' => 'Adjunct available only Tuesday and Thursday.']
        );
        AcademicPmcFacultyPreference::updateOrCreate(
            ['teacher_id' => $teacher->id, 'term_id' => $term?->id],
            ['faculty_type' => 'regular', 'available_days' => [1, 2, 3, 4, 5], 'preferred_slots' => [$slotOne->id, $slotTwo->id], 'unavailable_slots' => [], 'max_classes_per_day' => 4, 'max_consecutive_classes' => 3, 'max_weekly_load' => 18, 'subject_expertise' => [$subject->code, $labSubject->code]]
        );
        AcademicPmcFacultyAvailabilityRequest::updateOrCreate(
            ['teacher_id' => $adjunct->id, 'term_id' => $term?->id, 'request_type' => 'adjunct_availability'],
            ['status' => 'submitted', 'available_days' => [2, 4], 'preferred_slots' => [$slotOne->id], 'unavailable_slots' => [['day' => 1, 'slot_id' => $slotTwo->id]], 'max_classes_per_day' => 2, 'max_consecutive_classes' => 2, 'max_weekly_load' => 8, 'reason' => 'Visiting faculty available only Tuesday and Thursday.', 'submitted_by' => $adjunctUser->id, 'submitted_at' => now()->subDays(2), 'metadata' => ['version' => 'PMC OS v0.046']]
        );
        AcademicPmcFacultyAvailabilityRequest::updateOrCreate(
            ['teacher_id' => $teacher->id, 'term_id' => $term?->id, 'request_type' => 'regular_faculty_preference'],
            ['status' => 'approved', 'available_days' => [1, 2, 3, 4, 5], 'preferred_slots' => [$slotOne->id, $slotTwo->id], 'unavailable_slots' => [], 'max_classes_per_day' => 4, 'max_consecutive_classes' => 3, 'max_weekly_load' => 18, 'reason' => 'Prefers morning analytics lab slots.', 'submitted_by' => $facultyUser->id, 'submitted_at' => now()->subDays(5), 'decided_by' => $pmcHead->id, 'decided_at' => now()->subDays(4), 'decision_note' => 'Approved and applied to timetable generation.', 'metadata' => ['version' => 'PMC OS v0.046']]
        );
        AcademicPmcWorkloadRule::firstOrCreate(
            ['name' => 'PMC v0.041 Standard Faculty Load', 'program_id' => $program->id],
            ['term_id' => $term?->id, 'normal_weekly_hours' => 16, 'overload_threshold' => 18, 'underload_threshold' => 8, 'max_subjects' => 4, 'mentor_capacity' => 30, 'rules' => ['max_daily_classes' => 4, 'max_consecutive' => 3], 'is_active' => true]
        );

        AcademicPmcLockedSlot::firstOrCreate(
            ['title' => 'Dean mandated orientation block', 'day_of_week' => 1, 'timetable_slot_id' => $slotOne->id],
            ['slot_type' => 'orientation', 'program_id' => $program->id, 'batch_id' => $batch?->id, 'term_id' => $term?->id, 'classroom_id' => $room->id, 'is_hard_lock' => true, 'status' => 'active', 'created_by' => $dean->id, 'reason' => 'Orientation cannot be moved.']
        );
        AcademicPmcLockedSlot::firstOrCreate(
            ['title' => 'Guest lecture preferred block', 'day_of_week' => 4, 'timetable_slot_id' => $slotTwo->id],
            ['slot_type' => 'guest_lecture', 'program_id' => $program->id, 'batch_id' => $batch?->id, 'term_id' => $term?->id, 'course_group_id' => $electiveGroup->id, 'teacher_id' => $adjunct->id, 'classroom_id' => $room->id, 'is_hard_lock' => false, 'status' => 'active', 'created_by' => $pmcHead->id, 'reason' => 'Preferred slot for industry speaker.']
        );

        $version = TimetableVersion::updateOrCreate(
            ['program_id' => $program->id, 'term_id' => $term?->id, 'batch_id' => $batch?->id, 'version_number' => 41],
            ['status' => 'published', 'created_by' => $pmcHead->id, 'published_by' => $dean->id, 'published_at' => now(), 'effective_from' => now()->addWeek()->toDateString(), 'notes' => 'PMC v0.041 seeded timetable version; freeze state is tracked in v0.041 companion change records.']
        );
        $run = AcademicPmcTimetableGenerationRun::updateOrCreate(
            ['title' => 'PMC v0.041 Balanced Draft', 'program_id' => $program->id],
            ['strategy' => 'balanced', 'batch_id' => $batch?->id, 'term_id' => $term?->id, 'timetable_version_id' => $version->id, 'created_by' => $pmcHead->id, 'status' => 'conflict_review', 'scheduled_count' => 3, 'unscheduled_count' => 1, 'hard_conflict_count' => 1, 'soft_warning_count' => 3, 'quality_score' => 76, 'input_summary' => ['groups' => 3, 'strategy' => 'balanced', 'teaching_slots' => 3, 'break_slots' => 1, 'version' => 'PMC OS v0.064', 'strategy_aware_candidate_scoring' => true]]
        );
        $demandByGroup = collect();
        foreach ([[$coreGroup, 'lecture', 3, 1, 1, 2, 'partially_scheduled'], [$electiveGroup, 'lecture', 2, 1, 1, 1, 'partially_scheduled'], [$labGroup, 'lab', 1, 2, 1, 0, 'scheduled']] as [$group, $sessionType, $required, $duration, $scheduledDemand, $unscheduledDemand, $status]) {
            $demandByGroup[$group->id] = AcademicPmcTimetableSessionDemand::updateOrCreate(
                ['generation_run_id' => $run->id, 'course_group_id' => $group->id, 'session_type' => $sessionType],
                [
                    'required_sessions_per_week' => $required,
                    'duration_slots' => $duration,
                    'scheduled_sessions' => $scheduledDemand,
                    'unscheduled_sessions' => $unscheduledDemand,
                    'status' => $status,
                    'source' => 'seeded_weekly_session_demand',
                    'rules' => ['weekly_hours' => $required * $duration, 'version' => 'PMC OS v0.062'],
                    'metadata' => ['version' => 'PMC OS v0.062'],
                ]
            );
        }
        foreach ([[$coreGroup, $teacher, $room, 2, $slotOne, 'scheduled', 1, 88], [$electiveGroup, $adjunct, $parallelRoom, 2, $slotOne, 'scheduled', 1, 72], [$labGroup, $teacher, $lab, 3, $slotTwo, 'scheduled', 1, 84], [$electiveGroup, null, null, null, null, 'unscheduled', 2, 0]] as [$group, $itemTeacher, $itemRoom, $day, $slot, $status, $sessionIndex, $confidence]) {
            $isOfficialSession = in_array($status, ['scheduled', 'published', 'locked'], true);
            $sessionType = $demandByGroup[$group->id]?->session_type ?? 'lecture';
            $durationSlots = $demandByGroup[$group->id]?->duration_slots ?? 1;
            $metadata = [
                'version' => 'PMC OS v0.065',
                'placement_score' => $confidence,
                'placement_reasons' => $status === 'scheduled' ? ['strategy_balanced', 'seeded_strategy_ranked_candidate'] : [],
                'placement_alternatives' => $status === 'scheduled' ? [[
                    'day' => max(1, (int) $day),
                    'slot_id' => $slotTwo->id,
                    'slot_name' => $slotTwo->name,
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'score' => max(1, $confidence - 8),
                    'reasons' => ['seeded_alternative_candidate'],
                ]] : [],
                'canonical_official_session' => $isOfficialSession,
                'official_source' => 'academic_pmc_timetable_generation_items',
                'timetable_version_id' => $version->id,
            ];
            AcademicPmcTimetableGenerationItem::updateOrCreate(
                [
                    'generation_run_id' => $run->id,
                    'course_group_id' => $group->id,
                    'session_index' => $sessionIndex,
                    'session_type' => $sessionType,
                ],
                [
                    'timetable_version_id' => $version->id,
                    'program_id' => $group->program_id ?: $program->id,
                    'batch_id' => $group->batch_id ?: $batch?->id,
                    'term_id' => $group->term_id ?: $term?->id,
                    'subject_id' => $group->subject_id,
                    'session_demand_id' => $demandByGroup[$group->id]?->id,
                    'duration_slots' => $durationSlots,
                    'teacher_id' => $itemTeacher?->id,
                    'classroom_id' => $itemRoom?->id,
                    'day_of_week' => $day,
                    'timetable_slot_id' => $slot?->id,
                    'status' => $status,
                    'official_status' => $isOfficialSession ? 'published' : 'draft',
                    'source_type' => 'seeded_canonical_demo',
                    'published_at' => $isOfficialSession ? ($version->published_at ?? now()) : null,
                    'published_by' => $isOfficialSession ? ($version->published_by ?? $dean->id) : null,
                    'confidence' => $confidence,
                    'explanation' => $status === 'scheduled' ? 'Seeded deterministic placement with v0.065 strategy-aware ranking, alternatives, and break-safe multi-slot blocks.' : 'Adjunct day conflict requires alternate slot.',
                    'conflicts' => $status === 'unscheduled' ? ['adjunct_day_violation'] : [],
                    'metadata' => $metadata,
                ]
            );
        }
        AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->with('courseGroup')
            ->get()
            ->groupBy(fn ($item) => implode(':', [
                $item->courseGroup?->name ?? $item->course_group_id,
                $item->session_index,
                $item->session_type,
            ]))
            ->each(function ($duplicates) {
                $duplicates->sortBy(fn ($item) => ($item->source_type === 'seeded_canonical_demo' ? '0' : '1') . ':' . str_pad((string) $item->id, 10, '0', STR_PAD_LEFT))
                    ->slice(1)
                    ->each
                    ->delete();
            });
        AcademicPmcCourseGroup::whereIn('name', ['PGDM Core Section A', 'Growth Analytics Elective Group 1', 'Decision Analytics Lab Group L1'])
            ->where('program_id', $program->id)
            ->where('batch_id', $batch?->id)
            ->where('term_id', $term?->id)
            ->whereNotIn('id', [$coreGroup->id, $electiveGroup->id, $labGroup->id])
            ->get()
            ->each(function ($duplicateGroup) use ($coreGroup, $electiveGroup, $labGroup) {
                $keeper = collect([$coreGroup, $electiveGroup, $labGroup])->firstWhere('name', $duplicateGroup->name);
                if (! $keeper) {
                    return;
                }

                AcademicPmcTimetableGenerationItem::where('course_group_id', $duplicateGroup->id)->update(['course_group_id' => $keeper->id]);
                AcademicPmcTimetableSessionDemand::where('course_group_id', $duplicateGroup->id)->update(['course_group_id' => $keeper->id]);
                \Illuminate\Support\Facades\DB::table('academic_pmc_locked_slots')->where('course_group_id', $duplicateGroup->id)->update(['course_group_id' => $keeper->id]);
                \Illuminate\Support\Facades\DB::table('academic_pmc_substitution_recommendations')->where('course_group_id', $duplicateGroup->id)->update(['course_group_id' => $keeper->id]);
                \Illuminate\Support\Facades\DB::table('academic_pmc_course_group_members')->where('course_group_id', $duplicateGroup->id)->delete();
                \Illuminate\Support\Facades\DB::table('academic_pmc_group_faculty_assignments')->where('course_group_id', $duplicateGroup->id)->delete();
                $duplicateGroup->delete();
            });
        AcademicPmcTimetableConstraint::firstOrCreate(
            ['generation_run_id' => $run->id, 'constraint_type' => 'student_clash', 'affected_type' => 'student', 'affected_key' => (string) ($student?->id ?? 0)],
            ['severity' => 'hard', 'title' => 'Student clash through elective group membership', 'description' => 'Core section and elective group are both placed in the same slot for an overlapping student.', 'recommended_fix' => 'Move elective group or choose alternate section.', 'source_route' => route('academics.pmc.timetable-planner.index')]
        );
        $softConstraint = AcademicPmcTimetableConstraint::firstOrCreate(
            ['generation_run_id' => $run->id, 'constraint_type' => 'adjunct_day_preference', 'affected_type' => 'teacher', 'affected_key' => (string) $adjunct->id],
            ['severity' => 'soft', 'title' => 'Adjunct preferred day warning', 'description' => 'Adjunct faculty has a preferred Tuesday/Thursday pattern.', 'recommended_fix' => 'Review if Wednesday placement is unavoidable.', 'source_route' => route('academics.pmc.faculty-preferences.index')]
        );
        AcademicPmcTimetableConstraint::firstOrCreate(
            ['generation_run_id' => $run->id, 'constraint_type' => 'faculty_consecutive_load', 'affected_type' => 'teacher', 'affected_key' => (string) $teacher->id],
            ['severity' => 'soft', 'title' => 'Faculty consecutive teaching pressure', 'description' => 'Seeded v0.063 warning: faculty has too many consecutive teaching slots in one day.', 'recommended_fix' => 'Move one class away from the block or approve the exception.', 'source_route' => route('academics.pmc.timetable-planner.index')]
        );
        AcademicPmcTimetableConstraint::firstOrCreate(
            ['generation_run_id' => $run->id, 'constraint_type' => 'student_group_day_gaps', 'affected_type' => 'course_group', 'affected_key' => (string) $coreGroup->id],
            ['severity' => 'soft', 'title' => 'Student group day has avoidable gaps', 'description' => 'Seeded v0.063 warning: group timetable has avoidable mid-day gaps.', 'recommended_fix' => 'Move classes closer together or use a compact-student generation strategy.', 'source_route' => route('academics.pmc.timetable-planner.index')]
        );
        $hardConstraint = AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'student_clash')->first();
        foreach ([[$hardConstraint, 'move_group_slot', 'Move elective group away from core section', 'open', null], [$softConstraint, 'manual_resolution', 'Adjunct preferred day reviewed by PMC', 'resolved', 'Adjunct accepted the slot for this week.']] as [$constraint, $actionType, $title, $status, $note]) {
            if ($constraint) {
                AcademicPmcTimetableResolutionAction::updateOrCreate(
                    ['constraint_id' => $constraint->id, 'action_type' => $actionType],
                    ['generation_run_id' => $run->id, 'title' => $title, 'description' => $constraint->recommended_fix, 'owner_user_id' => $pmcOfficer->id, 'assigned_by' => $pmcHead->id, 'priority' => $constraint->severity === 'hard' ? 'high' : 'normal', 'status' => $status, 'due_at' => now()->addDay(), 'resolution_note' => $note, 'evidence' => $note ? ['method' => 'faculty_confirmation'] : null, 'closed_at' => $note ? now() : null, 'metadata' => ['version' => 'PMC OS v0.045']]
                );
            }
        }
        AcademicPmcTimetableQualityScore::updateOrCreate(
            ['generation_run_id' => $run->id],
            ['timetable_version_id' => $version->id, 'overall_score' => 76, 'hard_conflicts' => 1, 'soft_warnings' => 3, 'student_compactness_score' => 78, 'faculty_balance_score' => 74, 'room_utilization_score' => 84, 'details' => ['formula' => '100 - hard*15 - soft*4 plus balance deductions', 'version' => 'PMC OS v0.063', 'faculty_consecutive_checked' => true, 'student_group_day_gaps_checked' => true]]
        );

        foreach ([[$coreGroup, $teacher, 3, 1, 1, 1, 1, 62, 'critical', ['Group is behind planned delivery', 'Session delivery logs pending']], [$electiveGroup, $adjunct, 2, 1, 1, 0, 1, 48, 'high', ['Session delivery logs pending', 'Adjunct follow-up needed']], [$labGroup, $teacher, 2, 1, 0, 0, 1, 22, 'low', ['Lab delivery on track']]] as [$deliveryGroup, $deliveryTeacher, $planned, $conducted, $missed, $rescheduled, $pendingLogs, $riskScore, $riskBand, $reasons]) {
            $tracker = AcademicPmcGroupDeliveryTracker::updateOrCreate(
                ['course_group_id' => $deliveryGroup->id],
                [
                    'program_id' => $deliveryGroup->program_id,
                    'batch_id' => $deliveryGroup->batch_id,
                    'term_id' => $deliveryGroup->term_id,
                    'subject_id' => $deliveryGroup->subject_id,
                    'teacher_id' => $deliveryTeacher->id,
                    'owner_user_id' => $pmcOfficer->id,
                    'planned_sessions' => $planned,
                    'conducted_sessions' => $conducted,
                    'missed_sessions' => $missed,
                    'rescheduled_sessions' => $rescheduled,
                    'cancelled_sessions' => 0,
                    'pending_session_logs' => $pendingLogs,
                    'attendance_percent' => $riskBand === 'critical' ? 66.50 : 81.00,
                    'delivery_progress' => $planned > 0 ? (int) round(($conducted / $planned) * 100) : 0,
                    'risk_score' => $riskScore,
                    'risk_band' => $riskBand,
                    'status' => in_array($riskBand, ['critical', 'high'], true) ? 'action_required' : 'monitoring',
                    'next_review_at' => now()->addDays(in_array($riskBand, ['critical', 'high'], true) ? 2 : 10),
                    'risk_reasons' => $reasons,
                    'recommended_actions' => in_array($riskBand, ['critical', 'high'], true) ? ['collect faculty session logs', 'schedule group makeup class', 'start attendance intervention'] : ['continue monitoring'],
                    'metadata' => ['version' => 'Academics PMC OS v0.059'],
                ]
            );

            $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('course_group_id', $deliveryGroup->id)->where('status', 'scheduled')->get();
            foreach ($items as $index => $item) {
                $scheduledDate = now()->startOfWeek()->addDays(max(0, ($item->day_of_week ?? 1) - 1))->toDateTimeString();
                AcademicPmcSessionDeliveryLog::firstOrCreate(
                    ['generation_item_id' => $item->id, 'scheduled_date' => $scheduledDate],
                    [
                        'group_delivery_tracker_id' => $tracker->id,
                        'course_group_id' => $deliveryGroup->id,
                        'subject_id' => $deliveryGroup->subject_id,
                        'teacher_id' => $item->teacher_id,
                        'classroom_id' => $item->classroom_id,
                        'timetable_slot_id' => $item->timetable_slot_id,
                        'day_of_week' => $item->day_of_week,
                        'session_status' => $index === 0 && in_array($riskBand, ['critical', 'high'], true) ? 'missed' : 'conducted',
                        'delivery_type' => $deliveryGroup->group_type === 'lab_group' ? 'lab' : 'lecture',
                        'attendance_marked' => $index !== 0 || $riskBand === 'low',
                        'lesson_plan_updated' => $riskBand === 'low',
                        'material_uploaded' => $riskBand !== 'critical',
                        'topic_planned' => 'Seeded delivery topic for ' . ($deliveryGroup->subject?->name ?? 'course group'),
                        'topic_covered' => $index === 0 && in_array($riskBand, ['critical', 'high'], true) ? null : 'Topic covered as planned.',
                        'gap_reason' => $index === 0 && in_array($riskBand, ['critical', 'high'], true) ? 'Faculty log pending and makeup class required.' : null,
                        'metadata' => ['version' => 'Academics PMC OS v0.059'],
                    ]
                );
            }
        }

        ElectiveRegistrationWindow::updateOrCreate(
            ['program_id' => $program->id, 'term_id' => $term?->id, 'elective_group' => null],
            [
                'opens_at' => now()->subDays(3),
                'closes_at' => now()->addDays(7),
                'max_selections' => 3,
                'status' => 'open',
                'instructions' => 'Rank your preferred electives before PMC runs final allocation. Capacity and eligibility rules may move lower preferences to waitlist.',
                'created_by' => $pmcHead->id,
            ]
        );

        if ($student) {
            AcademicPmcElectiveChoice::updateOrCreate(
                ['student_id' => $student->id, 'term_id' => $term?->id, 'subject_id' => $elective->id],
                ['program_id' => $program->id, 'batch_id' => $batch?->id, 'preference_rank' => 1, 'priority_score' => 88, 'status' => 'allocated', 'choice_source' => 'student_choice', 'decision_reason' => 'Allocated in seeded v0.042 choice-window run.', 'metadata' => ['window' => 'Term 1 elective window', 'version' => 'PMC OS v0.091']]
            );
            AcademicPmcElectiveChoice::updateOrCreate(
                ['student_id' => $student->id, 'term_id' => $term?->id, 'subject_id' => $labSubject->id],
                ['program_id' => $program->id, 'batch_id' => $batch?->id, 'preference_rank' => 2, 'priority_score' => 70, 'status' => 'waitlisted', 'choice_source' => 'student_choice', 'decision_reason' => 'Capacity held for lab split group review.', 'metadata' => ['window' => 'Term 1 elective window', 'version' => 'PMC OS v0.091']]
            );
        }
        AcademicPmcGroupBuildRun::firstOrCreate(
            ['title' => 'PMC v0.042 Auto Group Build', 'subject_id' => $elective->id],
            ['program_id' => $program->id, 'batch_id' => $batch?->id, 'term_id' => $term?->id, 'group_type' => 'elective_group', 'strategy' => 'balanced_capacity', 'min_capacity' => 10, 'max_capacity' => 35, 'students_considered' => $student ? 1 : 0, 'groups_created' => 1, 'warnings_count' => 1, 'status' => 'completed_with_warnings', 'created_by' => $pmcHead->id, 'warnings' => ['Seeded elective group below minimum strength until full demo cohort is loaded.'], 'metadata' => ['version' => 'PMC OS v0.042']]
        );
        AcademicPmcTimetableSolverAttempt::updateOrCreate(
            ['generation_run_id' => $run->id, 'strategy' => 'balanced'],
            ['status' => 'completed_with_conflicts', 'placements_attempted' => 4, 'placements_scheduled' => 3, 'placements_unscheduled' => 1, 'hard_conflicts' => 1, 'soft_warnings' => 3, 'quality_score' => 76, 'diagnostics' => ['student_clash_checked' => true, 'adjunct_days_checked' => true, 'room_capacity_checked' => true, 'strategy_aware_candidate_scoring' => true, 'top_candidate_alternatives_stored' => true, 'version' => 'PMC OS v0.065']]
        );
        foreach ([[$teacher, 20, 5, 5, 18, 4, 'overload', 'approval_required', ['weekly_limit_exceeded']], [$adjunct, 6, 2, 1, 8, 2, 'normal', 'approved', []]] as [$loadTeacher, $hours, $classes, $maxDay, $weeklyLimit, $dailyLimit, $band, $status, $reasons]) {
            AcademicPmcFacultyLoadReview::updateOrCreate(
                ['teacher_id' => $loadTeacher->id, 'term_id' => $term?->id, 'generation_run_id' => $run->id],
                ['assigned_weekly_hours' => $hours, 'scheduled_classes' => $classes, 'max_classes_in_day' => $maxDay, 'max_consecutive_classes' => min($maxDay, 3), 'configured_weekly_limit' => $weeklyLimit, 'configured_daily_limit' => $dailyLimit, 'load_band' => $band, 'status' => $status, 'risk_reasons' => $reasons, 'daily_distribution' => [1 => min($classes, $maxDay), 2 => max(0, $classes - $maxDay)], 'reviewed_by' => $status === 'approved' ? $pmcHead->id : null, 'reviewed_at' => $status === 'approved' ? now()->subDay() : null, 'decision_note' => $status === 'approved' ? 'Normal adjunct load approved.' : null, 'metadata' => ['version' => 'PMC OS v0.047']]
            );
        }
        foreach ([[$room, 2, 1, 70, false, true, true, 'ready', 'approved', [], 'Lecture room capacity ready.'], [$lab, 1, 1, 35, true, true, true, 'ready', 'approved', [], 'Analytics lab readiness verified.']] as [$readyRoom, $classes, $strength, $capacity, $labRequired, $labReady, $capacityOk, $band, $status, $reasons, $note]) {
            AcademicPmcRoomReadinessReview::updateOrCreate(
                ['classroom_id' => $readyRoom->id, 'generation_run_id' => $run->id],
                ['scheduled_classes' => $classes, 'max_group_strength' => $strength, 'room_capacity' => $capacity, 'lab_required' => $labRequired, 'lab_ready' => $labReady, 'capacity_ok' => $capacityOk, 'readiness_band' => $band, 'status' => $status, 'risk_reasons' => $reasons, 'usage_distribution' => [2 => $classes], 'reviewed_by' => $pmcHead->id, 'reviewed_at' => now()->subDay(), 'decision_note' => $note, 'metadata' => ['version' => 'PMC OS v0.048']]
            );
        }
        foreach ([
            ['generated_operational_sync', 'timetable', 'ok', 'Published generated classes synced to operational timetable', 3, 3, 0, 'Operational timetable sync is healthy.'],
            ['allocation_enrollment_links', 'course_basket', 'warn', 'Approved allocations linked to student subject enrollments', 3, 2, 1, 'Refresh course basket enrollment links before final lock.'],
            ['scheduled_groups_delivery_trackers', 'course_delivery', 'ok', 'Scheduled course groups have delivery trackers', 3, 3, 0, 'Delivery tracker coverage is healthy.'],
        ] as [$key, $group, $status, $title, $expected, $actual, $mismatch, $action]) {
            AcademicPmcDataReconciliationCheck::updateOrCreate(
                ['check_key' => $key, 'source_type' => 'global', 'source_key' => 'all'],
                [
                    'check_group' => $group,
                    'status' => $status,
                    'severity' => $status === 'ok' ? 'low' : 'medium',
                    'title' => $title,
                    'description' => 'Seeded PMC v0.092 reconciliation baseline.',
                    'expected_count' => $expected,
                    'actual_count' => $actual,
                    'mismatch_count' => $mismatch,
                    'recommended_action' => $action,
                    'details' => ['version' => 'PMC OS v0.092', 'source' => 'demo_seed'],
                    'checked_by' => $pmcHead->id,
                    'checked_at' => now()->subHours(2),
                ]
            );
        }
        AcademicPmcDataReconciliationRun::where('source', 'demo_seed')->delete();
        $demoReconciliationRuns = [];
        foreach ([
            ['completed', false, now()->subHours(6), now()->subHours(6)->addMinutes(2), 5, 1, 0, 0, null, ['note' => 'Hourly scheduler refreshed PMC reconciliation checks.']],
            ['completed', true, now()->subHours(3), now()->subHours(3)->addMinutes(4), 5, 1, 0, 2, null, ['repair_messages' => ['2 allocation enrollment links repaired.']]],
            ['failed', false, now()->subHours(1), now()->subMinutes(58), 0, 0, 0, 0, 'Demo failed run showing scheduler failure visibility.', ['exception' => 'DemoSchedulerException']],
            ['running', false, now()->subMinutes(75), null, 0, 0, 0, 0, null, ['note' => 'Demo stale running run for scheduler health warning.']],
        ] as [$status, $repairRequested, $startedAt, $finishedAt, $checks, $mismatches, $critical, $repaired, $failure, $metadata]) {
            $demoReconciliationRuns[] = AcademicPmcDataReconciliationRun::create([
                'source' => 'demo_seed',
                'status' => $status,
                'repair_requested' => $repairRequested,
                'started_by' => $pmcHead->id,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'checks_count' => $checks,
                'mismatch_count' => $mismatches,
                'critical_count' => $critical,
                'repaired_count' => $repaired,
                'failure_reason' => $failure,
                'metadata' => array_merge(['version' => 'PMC OS v0.098'], $metadata),
            ]);
        }
        $allocationCheck = AcademicPmcDataReconciliationCheck::where('check_key', 'allocation_enrollment_links')->first();
        foreach ([
            [
                'action' => 'academic_pmc_v092_data_reconciliation_refreshed',
                'description' => 'Demo reconciliation refresh audit for seeded PMC data health.',
                'subject' => $demoReconciliationRuns[0] ?? null,
                'metadata' => ['version' => 'PMC OS v0.109', 'checks' => 5, 'mismatches' => 1, 'reason' => 'Demo scheduled reconciliation refresh completed.'],
                'created_at' => now()->subHours(5),
            ],
            [
                'action' => 'academic_pmc_v093_data_reconciliation_repaired',
                'description' => 'Demo reconciliation repair audit for allocation enrollment links.',
                'subject' => $allocationCheck,
                'metadata' => ['version' => 'PMC OS v0.109', 'check_key' => 'allocation_enrollment_links', 'message' => '2 allocation enrollment links repaired.'],
                'created_at' => now()->subHours(3),
            ],
            [
                'action' => 'academic_pmc_v105_reconciliation_stale_run_closed',
                'description' => 'Demo stale reconciliation run closure audit.',
                'subject' => $demoReconciliationRuns[2] ?? null,
                'metadata' => ['version' => 'PMC OS v0.109', 'reason' => 'Demo stale scheduler run was closed after process check.', 'source' => 'demo_seed'],
                'created_at' => now()->subMinutes(50),
            ],
        ] as $audit) {
            DepartmentActivityLog::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'action' => $audit['action'],
                    'description' => $audit['description'],
                ],
                [
                    'actor_user_id' => $pmcHead->id,
                    'subject_type' => $audit['subject'] ? get_class($audit['subject']) : null,
                    'subject_id' => $audit['subject']?->id,
                    'metadata' => $audit['metadata'],
                    'created_at' => $audit['created_at'],
                    'updated_at' => $audit['created_at'],
                ]
            );
        }
        foreach ([['hard_conflicts', 'block', 'critical', 'Hard conflicts before publish', 'Student clash exists through overlapping group membership.', 'pmc_head'], ['quality_score', 'pass', 'info', 'Quality threshold', 'Score is above publish threshold after conflict resolution.', 'pmc_head'], ['dean_after_freeze', 'warn', 'medium', 'Dean approval after freeze', 'Post-freeze changes require Dean approval.', 'dean_academics']] as [$type, $status, $severity, $title, $description, $role]) {
            AcademicPmcTimetablePublishCheck::firstOrCreate(
                ['generation_run_id' => $run->id, 'check_type' => $type],
                ['status' => $status, 'severity' => $severity, 'title' => $title, 'description' => $description, 'required_role' => $role, 'metadata' => ['version' => 'PMC OS v0.042']]
            );
        }
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $run->id, 'check_type' => 'room_readiness'],
            ['status' => 'pass', 'severity' => 'info', 'title' => 'Room and lab readiness', 'description' => 'Seeded rooms/labs are reviewed for v0.048 publish readiness.', 'required_role' => 'pmc_head', 'metadata' => ['version' => 'PMC OS v0.048']]
        );
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => null, 'check_type' => 'faculty_acknowledgements'],
            ['status' => 'warn', 'severity' => 'medium', 'title' => 'Faculty assignment acknowledgements', 'description' => 'One adjunct acknowledgement has constraints pending PMC review.', 'required_role' => 'pmc_head', 'metadata' => ['version' => 'PMC OS v0.052']]
        );
        AcademicPmcTimetableVersionWorkflow::updateOrCreate(
            ['timetable_version_id' => $version->id],
            [
                'generation_run_id' => $run->id,
                'lifecycle_status' => 'frozen',
                'approval_status' => 'dean_frozen',
                'published_by' => $dean->id,
                'published_at' => $version->published_at ?? now(),
                'frozen_by' => $dean->id,
                'frozen_at' => now()->addDay(),
                'decision_reason' => 'Seeded v0.043 Dean freeze after PMC timetable impact review.',
                'publish_summary' => ['scheduled' => 3, 'unscheduled' => 1, 'hard_conflicts' => 1, 'soft_warnings' => 1, 'quality_score' => 78],
                'impact_summary' => ['affected_groups' => 3, 'affected_students' => $student ? 1 : 0, 'affected_faculty' => 2],
                'metadata' => ['version' => 'PMC OS v0.043'],
            ]
        );

        $change = AcademicPmcTimetableChangeRequest::firstOrCreate(
            ['timetable_version_id' => $version->id, 'change_type' => 'revision', 'reason' => 'Move elective away from student clash.'],
            ['status' => 'requested', 'requested_by' => $pmcHead->id, 'impact_summary' => ['students' => 1, 'faculty' => 1, 'rooms' => 1]]
        );
        foreach ([['students', 'Affected student cohort', 1], ['faculty', 'Affected adjunct faculty', 1], ['rooms', 'Room change candidate', 1], ['groups', 'Elective group impacted', 1]] as [$type, $title, $count]) {
            AcademicPmcTimetableImpactRecord::firstOrCreate(
                ['change_request_id' => $change->id, 'impact_type' => $type, 'title' => $title],
                ['affected_count' => $count, 'affected_records' => ['source' => 'seeded_v041']]
            );
        }
        AcademicPmcSubstitutionRecommendation::firstOrCreate(
            ['course_group_id' => $coreGroup->id, 'original_teacher_id' => $teacher->id, 'substitution_date' => now()->addDays(2)->toDateString()],
            ['substitute_teacher_id' => $adjunct->id, 'status' => 'recommended', 'score' => 74, 'reasons' => ['subject_expertise_overlap', 'available_day_clear'], 'conflict_checks' => ['faculty' => 'clear', 'student' => 'clear', 'room' => 'clear']]
        );
        foreach ([['publish', 'students', 'Timetable draft ready for review'], ['revision', 'faculty', 'Elective slot revision proposed'], ['substitution', 'faculty', 'Substitution recommendation queued']] as [$type, $recipient, $title]) {
            AcademicPmcTimetableNotification::firstOrCreate(
                ['notification_type' => $type, 'recipient_type' => $recipient, 'title' => $title],
                ['recipient_user_id' => $recipient === 'faculty' ? $facultyUser->id : null, 'message' => 'Seeded PMC v0.041 timetable notification.', 'status' => 'queued', 'source_type' => 'pmc_timetable_v041', 'source_key' => $run->id]
            );
        }
        AcademicPmcTimetableNotification::updateOrCreate(
            ['notification_type' => 'publish', 'recipient_type' => 'student', 'title' => 'Failed student timetable publish notice'],
            [
                'recipient_user_id' => $student?->user_id,
                'message' => 'Seeded failed notice for PMC v0.075 retry workflow.',
                'status' => 'failed',
                'source_type' => 'pmc_timetable_v075',
                'source_key' => $run->id,
                'metadata' => [
                    'version' => 'PMC OS v0.075',
                    'generation_run_id' => $run->id,
                    'quality_score' => 78,
                    'hard_conflicts' => 1,
                    'soft_warnings' => 2,
                    'latest_status_note' => 'Sandbox delivery provider timeout.',
                    'latest_status_changed_at' => now()->subMinutes(30)->toDateTimeString(),
                    'status_history' => [
                        ['from' => 'queued', 'to' => 'failed', 'note' => 'Sandbox delivery provider timeout.', 'actor_user_id' => $pmcHead->id, 'changed_at' => now()->subMinutes(30)->toDateTimeString()],
                    ],
                ],
            ]
        );

        foreach (['course-allocation', 'course-groups', 'section-faculty-allocation', 'locked-slots', 'timetable-generator', 'timetable-planner', 'timetable-versions-v041', 'substitution-intelligence', 'timetable-reports'] as $surface) {
            AcademicPmcSavedView::firstOrCreate(
                ['user_id' => $pmcHead->id, 'surface' => $surface, 'name' => 'v0.041 ' . str($surface)->headline()],
                ['filters' => ['program_id' => $program->id], 'is_default' => $surface === 'timetable-planner']
            );
            AcademicPmcPolicyAudit::updateOrCreate(
                ['route_name' => 'academics.pmc.' . $surface . '.index'],
                ['method' => 'GET', 'required_scope' => 'pmc_timetable_scope', 'risk_level' => str_contains($surface, 'versions') ? 'high' : 'medium', 'middleware_present' => true, 'policy_present' => true, 'last_test_status' => 'passed', 'missing_enforcement' => false, 'roles_tested' => ['dean_academics', 'pmc_head', 'pmc_manager', 'pmc_officer', 'student'], 'metadata' => ['version' => 'PMC OS v0.041']]
            );
        }

        DepartmentActivityLog::firstOrCreate(
            ['department_id' => $department->id, 'action' => 'academics_pmc_timetable_v041_seeded', 'description' => 'Seeded PMC OS v0.041 timetable allocation, section/group, faculty load, constraints, versioning, substitution, and notification data.'],
            ['actor_user_id' => $pmcHead->id, 'metadata' => ['version' => 'PMC OS v0.041']]
        );
    }
}
