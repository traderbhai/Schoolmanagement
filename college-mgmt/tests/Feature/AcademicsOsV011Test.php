<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\AcademicAttentionService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsOsV011Test extends TestCase
{
    use RefreshDatabase;

    private function academicFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'is_active' => true]);
        $semester = Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $student = Student::factory()->create(['department_id' => $department->id, 'program_id' => $program->id]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        $change = CurriculumChange::firstOrCreate(
            ['program_id' => $program->id, 'title' => 'Add applied case lab'],
            [
                'subject_id' => $subject->id,
                'proposed_by' => User::where('email', 'chair@college.com')->firstOrFail()->id,
                'description' => 'Add a practical case lab to strengthen application outcomes.',
                'change_type' => 'modify_syllabus',
                'status' => 'submitted',
                'submitted_at' => now()->subDays(2),
            ]
        );

        ApprovalWorkflow::firstOrCreate(
            ['approvable_type' => CurriculumChange::class, 'approvable_id' => $change->id, 'approver_role' => 'dean_academics'],
            ['status' => 'pending', 'sla_days' => 1, 'due_at' => now()->subDay()]
        );

        Exam::firstOrCreate(
            ['name' => 'Pending Marks Test', 'subject_id' => $subject->id],
            [
                'semester_id' => $semester->id,
                'program_id' => $program->id,
                'type' => 'internal',
                'exam_date' => now()->subDays(3)->toDateString(),
                'total_marks' => 30,
                'passing_marks' => 12,
            ]
        );

        return compact('department', 'program', 'subject', 'semester', 'student');
    }

    public function test_dean_command_center_shows_all_branch_attention_queues(): void
    {
        $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.command-center.index'))
            ->assertOk()
            ->assertSee('Academics Command Center')
            ->assertSee('Pending academic approvals')
            ->assertSee('Curriculum changes pending')
            ->assertSee('Exam marks pending')
            ->assertSee('OBE mapping gaps');
    }

    public function test_role_workspaces_show_branch_specific_queues(): void
    {
        $this->academicFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $exam = User::where('email', 'exam@college.com')->firstOrFail();
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.workspaces.show', 'pmc'))
            ->assertOk()
            ->assertSee('PMC Workspace')
            ->assertSee('Curriculum changes pending')
            ->assertDontSee('Exam marks pending');

        $this->actingAs($exam)
            ->get(route('academics.workspaces.show', 'coe'))
            ->assertOk()
            ->assertSee('CoE / Examination Workspace')
            ->assertSee('Exam marks pending')
            ->assertDontSee('Curriculum changes pending');

        $this->actingAs($iqac)
            ->get(route('academics.workspaces.show', 'iqac'))
            ->assertOk()
            ->assertSee('IQAC Workspace')
            ->assertSee('OBE mapping gaps')
            ->assertSee('Feedback collection pending');
    }

    public function test_queue_numbers_link_to_filtered_source_list(): void
    {
        $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.attention.queue', 'curriculum_changes'))
            ->assertOk()
            ->assertSee('Curriculum changes pending')
            ->assertSee('Filtered Source List')
            ->assertSee('Add applied case lab');
    }

    public function test_attention_queue_empty_state_guides_staff_and_invalid_queue_is_blocked(): void
    {
        $this->academicFixture();
        ProgramOutcome::query()->delete();
        CourseOutcome::query()->delete();
        Program::where('is_active', true)->get()->each(fn (Program $program) => ProgramOutcome::create([
            'program_id' => $program->id,
            'code' => 'PO1',
            'description' => 'Program outcome coverage for empty-queue UX test.',
            'category' => 'management',
        ]));
        Subject::where('is_active', true)->get()->each(fn (Subject $subject) => CourseOutcome::create([
            'subject_id' => $subject->id,
            'code' => 'CO1',
            'description' => 'Course outcome coverage for empty-queue UX test.',
            'bloom_level' => 'understand',
        ]));
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.attention.queue', 'obe_mapping_gaps'))
            ->assertOk()
            ->assertSee('OBE mapping gaps')
            ->assertSee('No open records for this queue')
            ->assertSee('This means your current Academics scope has no unresolved items for')
            ->assertSee('Review the Command Center for other branches')
            ->assertDontSee('No records currently match this queue.');

        $this->actingAs($dean)
            ->get(route('academics.attention.queue', 'not_a_real_queue'))
            ->assertNotFound();
    }

    public function test_attention_queues_show_people_names_instead_of_raw_ids(): void
    {
        $fixture = $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $facultyUser = User::factory()->create(['name' => 'Prof Real Workload']);
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $teacher = Teacher::factory()->create([
            'user_id' => $facultyUser->id,
            'department_id' => $fixture['department']->id,
            'employee_id' => 'FAC-REAL-01',
        ]);

        foreach (range(1, 5) as $day) {
            TimetableEntry::factory()->create([
                'semester_id' => $fixture['semester']->id,
                'course_id' => $course->id,
                'program_id' => $fixture['program']->id,
                'subject_id' => $fixture['subject']->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => $day,
                'is_active' => true,
            ]);
        }

        $studentUser = User::factory()->create(['name' => 'Ananya Attendance Risk']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
        ]);
        $entry = TimetableEntry::where('teacher_id', $teacher->id)->firstOrFail();
        Attendance::create(['student_id' => $student->id, 'timetable_entry_id' => $entry->id, 'date' => now()->subDays(2), 'status' => 'absent']);
        Attendance::create(['student_id' => $student->id, 'timetable_entry_id' => $entry->id, 'date' => now()->subDay(), 'status' => 'late']);

        $this->actingAs($dean)
            ->get(route('academics.attention.queue', 'faculty_workload'))
            ->assertOk()
            ->assertSee('Prof Real Workload')
            ->assertDontSee('Teacher #'.$teacher->id);

        $this->actingAs($dean)
            ->get(route('academics.attention.queue', 'attendance_risk'))
            ->assertOk()
            ->assertSee('Ananya Attendance Risk')
            ->assertDontSee('Student #'.$student->id);
    }

    public function test_attention_service_respects_program_scope_for_program_leadership(): void
    {
        $fixture = $this->academicFixture();
        $visibleSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'is_active' => true,
            'name' => 'Visible Scoped Subject Without Faculty',
        ]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        Subject::factory()->create(['program_id' => $otherProgram->id, 'is_active' => true, 'name' => 'Hidden Scope Subject']);

        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $queues = app(AcademicAttentionService::class)->queuesFor($chair, 'pmc');
        $subjectsWithoutFaculty = collect($queues)->firstWhere('key', 'subjects_without_faculty');

        $this->assertNotNull($subjectsWithoutFaculty);
        $this->assertFalse(collect($subjectsWithoutFaculty['items'])->pluck('title')->contains('Hidden Scope Subject'));
    }

    public function test_attention_service_scopes_approval_queues_for_program_leadership(): void
    {
        $fixture = $this->academicFixture();
        $otherProgram = Program::factory()->create(['code' => 'HIDDEN-APPROVAL', 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $member = \App\Models\DepartmentMember::where('user_id', $chair->id)->firstOrFail();
        app(\App\Services\AcademicScopeService::class)->assign(
            $chair,
            $member,
            'program',
            $fixture['program']->id,
            $fixture['program']->code,
            $fixture['program']->name ?? $fixture['program']->code,
            'test_program_approval_scope',
            true
        );
        ApprovalWorkflow::query()->delete();

        $visibleChange = CurriculumChange::create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'proposed_by' => $chair->id,
            'title' => 'Visible Scoped Approval',
            'description' => 'Visible program approval',
            'change_type' => 'modify_syllabus',
            'status' => 'submitted',
            'submitted_at' => now()->subDays(2),
        ]);
        $hiddenChange = CurriculumChange::create([
            'program_id' => $otherProgram->id,
            'proposed_by' => $chair->id,
            'title' => 'Hidden Foreign Approval',
            'description' => 'Foreign program approval',
            'change_type' => 'modify_syllabus',
            'status' => 'submitted',
            'submitted_at' => now()->subDays(2),
        ]);

        ApprovalWorkflow::create([
            'approvable_type' => CurriculumChange::class,
            'approvable_id' => $visibleChange->id,
            'approver_role' => 'dean_academics',
            'status' => 'pending',
            'due_at' => now()->subDay(),
        ]);
        ApprovalWorkflow::create([
            'approvable_type' => CurriculumChange::class,
            'approvable_id' => $hiddenChange->id,
            'approver_role' => 'dean_academics',
            'status' => 'pending',
            'due_at' => now()->subDay(),
        ]);

        $queues = collect(app(AcademicAttentionService::class)->queuesFor($chair, 'command'));
        $pendingSubtitles = collect($queues->firstWhere('key', 'pending_approvals')['items'])->pluck('subtitle');
        $overdueSubtitles = collect($queues->firstWhere('key', 'overdue_approvals')['items'])->pluck('title');

        $this->assertTrue($pendingSubtitles->contains('CurriculumChange #'.$visibleChange->id));
        $this->assertFalse($pendingSubtitles->contains('CurriculumChange #'.$hiddenChange->id));
        $this->assertTrue($overdueSubtitles->contains('ApprovalWorkflow #'.ApprovalWorkflow::where('approvable_id', $visibleChange->id)->where('approvable_type', CurriculumChange::class)->value('id')));
        $this->assertFalse($overdueSubtitles->contains('ApprovalWorkflow #'.ApprovalWorkflow::where('approvable_id', $hiddenChange->id)->where('approvable_type', CurriculumChange::class)->value('id')));
    }

    public function test_attention_service_attendance_risk_ignores_draft_timetable_history(): void
    {
        $fixture = $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);

        $officialStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Official Attention Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);
        $draftOnlyStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft Only Attention Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $officialStudent->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => $day === 1 ? 'absent' : 'late',
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $draftOnlyStudent->id,
                'timetable_entry_id' => $draftEntry->id,
                'date' => now()->subDays($day + 3)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $queue = app(AcademicAttentionService::class)->queue($dean, 'attendance_risk');
        $titles = collect($queue['items'])->pluck('title');

        $this->assertTrue($titles->contains('Official Attention Risk'));
        $this->assertFalse($titles->contains('Draft Only Attention Risk'));
    }

    public function test_attention_service_treats_draft_version_timetable_entries_as_unpublished(): void
    {
        $fixture = $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $teacherUser = User::factory()->create(['name' => 'Draft Version Workload Faculty']);
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $fixture['department']->id,
        ]);
        $term = \App\Models\Term::factory()->create(['program_id' => $fixture['program']->id]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 9,
            'status' => 'draft',
            'created_by' => $dean->id,
        ]);

        foreach (range(1, 5) as $index) {
            TimetableEntry::factory()->create([
                'semester_id' => $fixture['semester']->id,
                'course_id' => $course->id,
                'program_id' => $fixture['program']->id,
                'term_id' => $term->id,
                'subject_id' => $fixture['subject']->id,
                'teacher_id' => $teacher->id,
                'timetable_slot_id' => TimetableSlot::factory()->create([
                    'start_time' => sprintf('%02d:00:00', 8 + $index),
                    'end_time' => sprintf('%02d:00:00', 9 + $index),
                    'sort_order' => $index,
                ])->id,
                'day_of_week' => $index,
                'is_active' => true,
                'status' => 'published',
                'timetable_version_id' => $draftVersion->id,
            ]);
        }

        $attention = app(AcademicAttentionService::class);
        $workloadTitles = collect($attention->queue($dean, 'faculty_workload')['items'])->pluck('title');
        $draftTitles = collect($attention->queue($dean, 'draft_timetable')['items'])->pluck('title');

        $this->assertFalse($workloadTitles->contains('Draft Version Workload Faculty'));
        $this->assertTrue($draftTitles->contains($fixture['subject']->name));
    }

    public function test_attention_service_result_publish_pending_excludes_already_published_exams(): void
    {
        $fixture = $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $unpublishedExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'name' => 'Unpublished Result Review',
            'exam_date' => now()->subDay()->toDateString(),
            'published_at' => null,
        ]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'name' => 'Already Published Result Review',
            'exam_date' => now()->subDay()->toDateString(),
            'published_at' => now(),
        ]);

        ExamResult::factory()->create([
            'exam_id' => $unpublishedExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 18,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 20,
        ]);

        $queue = app(AcademicAttentionService::class)->queue($dean, 'result_publish_pending');
        $titles = collect($queue['items'])->pluck('title');

        $this->assertTrue($titles->contains('Unpublished Result Review'));
        $this->assertFalse($titles->contains('Already Published Result Review'));
    }

    public function test_attention_service_exam_marks_pending_excludes_already_published_exams(): void
    {
        $fixture = $this->academicFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'name' => 'Unpublished Missing Marks',
            'exam_date' => now()->subDay()->toDateString(),
            'published_at' => null,
        ]);
        Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'name' => 'Published Missing Marks',
            'exam_date' => now()->subDay()->toDateString(),
            'published_at' => now(),
        ]);

        $queue = app(AcademicAttentionService::class)->queue($dean, 'exam_marks_pending');
        $titles = collect($queue['items'])->pluck('title');

        $this->assertTrue($titles->contains('Unpublished Missing Marks'));
        $this->assertFalse($titles->contains('Published Missing Marks'));
    }

    public function test_unauthorized_user_cannot_open_academics_command_center(): void
    {
        $this->academicFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('student');

        $this->actingAs($studentUser)
            ->get(route('academics.command-center.index'))
            ->assertForbidden();
    }
}
