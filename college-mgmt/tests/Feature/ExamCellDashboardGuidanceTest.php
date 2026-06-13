<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAnomalyLog;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamCellDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function examCellUser(): User
    {
        Role::firstOrCreate(['name' => 'exam_cell', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('exam_cell');

        return $user;
    }

    public function test_exam_cell_dashboard_prioritizes_completed_exams_without_results(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);

        Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'name' => 'Internal Assessment 1',
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.dashboard'))
            ->assertStatus(200)
            ->assertSee('Exam Cell Priority')
            ->assertSee('Enter results for 1 completed exam')
            ->assertSee('Enter Results')
            ->assertSee(route('exam-cell.results'), false)
            ->assertSee(route('exam-cell.exams.create'), false)
            ->assertDontSee(route('admin.exams.create'), false);
    }

    public function test_exam_cell_dashboard_prioritizes_open_anomalies(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'name' => 'End Semester Exam',
            'exam_date' => now()->subDay()->toDateString(),
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);

        ExamAnomalyLog::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'anomaly_type' => 'malpractice',
            'description' => 'Suspicious activity reported by invigilator.',
            'severity' => 'high',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.dashboard'))
            ->assertStatus(200)
            ->assertSee('Resolve 1 open exam anomaly')
            ->assertSee('Review Anomalies')
            ->assertSee(route('exam-cell.anomalies.index'), false)
            ->assertDontSee('Enter results for 1 completed exam');
    }
}
