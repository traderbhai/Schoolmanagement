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
use App\Models\AcademicPmcReviewMeeting;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\AcademicPmcTimetableControl;
use App\Models\AcademicPmcWorkItem;
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
use App\Models\User;
use App\Services\AcademicScopeService;
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
        $student = Student::first();
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

        $this->seedPmcOperatingSignals($department, $pmcHead, $program, $subject, $student, $semester, $termId);
        $this->seedPmcV003Signals($department, $pmcHead, $program, $subject, $student, $termId);
        $this->seedIqacOperatingSignals($department, $iqacHead, $program, $subject, $student, $termId);
        $this->seedCourseDeliverySignals($department, $pmcHead, $program, $subject, $student, $semester, $termId);
        $this->seedDeanOperatingSignals($department, $dean, $pmcHead, $coe, $iqacHead, $program);
        $this->seedDeanV008Signals($department, $dean, $pmcHead, $coe, $iqacHead, $program, $subject, $student);
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

    private function seedDeanV008Signals(Department $department, User $dean, User $pmcHead, User $coe, User $iqacHead, ?Program $program, ?Subject $subject, ?Student $student): void
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

    private function seedCourseDeliverySignals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?Semester $semester, ?int $termId): void
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

    private function seedIqacOperatingSignals(Department $department, User $iqacHead, ?Program $program, ?Subject $subject, ?Student $student, ?int $termId): void
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

    private function seedPmcOperatingSignals(Department $department, User $pmcHead, ?Program $program, ?Subject $subject, ?Student $student, ?Semester $semester, ?int $termId): void
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
