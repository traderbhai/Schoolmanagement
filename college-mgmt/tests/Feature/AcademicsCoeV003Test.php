<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicCoeOperatingService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsCoeV003Test extends TestCase
{
    use RefreshDatabase;

    private function seedCoeFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $semester = Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $studentUser = User::factory()->create(['name' => 'Kavya Nair']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'semester', 'student');
    }

    public function test_exam_cell_user_can_open_coe_operating_dashboard(): void
    {
        $this->seedCoeFixture();
        $exam = User::where('email', 'exam@college.com')->firstOrFail();

        $this->actingAs($exam)
            ->get(route('academics.coe.index'))
            ->assertOk()
            ->assertSee('CoE Operating System')
            ->assertSee('Exam Readiness')
            ->assertSee('Marks And Results')
            ->assertSee('Hall Tickets')
            ->assertSee('Appeals And Anomalies')
            ->assertSee('End Term Operations Demo');
    }

    public function test_coe_source_lists_are_database_backed_and_linked_to_exam_cell_workflows(): void
    {
        $fixture = $this->seedCoeFixture();
        $exam = User::where('email', 'exam@college.com')->firstOrFail();

        $this->actingAs($exam)
            ->get(route('academics.coe.hall-ticket-readiness'))
            ->assertOk()
            ->assertSee('Hall Ticket Readiness')
            ->assertSee('Filtered Source List')
            ->assertSee('Kavya Nair')
            ->assertDontSee('Student #'.$fixture['student']->id)
            ->assertSee(route('exam-cell.hall-tickets'), false);

        $this->actingAs($exam)
            ->get(route('academics.coe.appeals-anomalies'))
            ->assertOk()
            ->assertSee('Appeals And Anomalies')
            ->assertSee('Rechecking requested')
            ->assertSee('attendance_mismatch');
    }

    public function test_coe_reports_page_lists_operational_reports(): void
    {
        $this->seedCoeFixture();
        $exam = User::where('email', 'exam@college.com')->firstOrFail();

        $this->actingAs($exam)
            ->get(route('academics.coe.reports'))
            ->assertOk()
            ->assertSee('CoE Reports')
            ->assertSee('Exam readiness')
            ->assertSee('Marks and results')
            ->assertSee('Hall ticket readiness');
    }

    public function test_coe_service_respects_program_scope_for_exam_manager(): void
    {
        $this->seedCoeFixture();
        $otherProgram = Program::factory()->create(['code' => 'BBA-HIDDEN', 'is_active' => true]);
        Subject::factory()->create(['program_id' => $otherProgram->id, 'name' => 'Hidden Exam Subject', 'is_active' => true]);

        $manager = User::where('email', 'exam.manager@college.com')->firstOrFail();
        $data = app(AcademicCoeOperatingService::class)->examReadiness($manager);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('End Term Operations Demo'));
        $this->assertFalse($titles->contains('Hidden Exam Subject'));
    }

    public function test_coe_official_result_metrics_use_exam_publication_boundary(): void
    {
        $fixture = $this->seedCoeFixture();
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();
        $service = app(AcademicCoeOperatingService::class);
        $beforeMarks = $service->marksResults($examUser)['metrics'];
        $beforeTranscripts = $service->transcripts($examUser)['metrics'];

        $draftExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['subject']->id,
            'exam_date' => now()->subDay(),
            'published_at' => null,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 30,
            'remarks' => 'Published',
            'is_absent' => false,
        ]);

        $afterDraftMarks = $service->marksResults($examUser)['metrics'];
        $afterDraftTranscripts = $service->transcripts($examUser)['metrics'];
        $this->assertSame($beforeMarks['published_exams'], $afterDraftMarks['published_exams']);
        $this->assertSame($beforeTranscripts['result_records'], $afterDraftTranscripts['result_records']);

        $publishedExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['subject']->id,
            'exam_date' => now()->subDay(),
            'published_at' => now(),
        ]);
        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 75,
            'is_absent' => false,
        ]);

        $afterPublishedMarks = $service->marksResults($examUser)['metrics'];
        $afterPublishedTranscripts = $service->transcripts($examUser)['metrics'];
        $this->assertSame($beforeMarks['published_exams'] + 1, $afterPublishedMarks['published_exams']);
        $this->assertSame($beforeTranscripts['result_records'] + 1, $afterPublishedTranscripts['result_records']);
    }

    public function test_non_academic_user_cannot_access_coe_operating_system(): void
    {
        $this->seedCoeFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.coe.index'))
            ->assertForbidden();
    }
}
