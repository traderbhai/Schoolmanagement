<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CurriculumChange;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
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
