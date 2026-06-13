<?php

namespace Tests\Feature;

use App\Models\{User, Student, Teacher, Program, Subject, Exam, ExamResult};
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_service_O_grade(): void
    {
        $gradeService = new GradeService();
        $grade = $gradeService->getGrade(92);
        $this->assertEquals('O', $grade['letter']);
    }

    public function test_grade_service_F_grade(): void
    {
        $gradeService = new GradeService();
        $grade = $gradeService->getGrade(30);
        $this->assertEquals('F', $grade['letter']);
    }

    public function test_grade_service_B_grade(): void
    {
        $gradeService = new GradeService();
        $grade = $gradeService->getGrade(57);
        $this->assertContains($grade['letter'], ['B', 'B+', 'C']);
    }

    public function test_teacher_can_view_exam_list(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $tUser   = User::factory()->create();
        $tUser->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $tUser->id]);

        $response = $this->actingAs($tUser)->get(route('teacher.exams.index'));
        $response->assertStatus(200);
    }

    public function test_student_can_view_results(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $sUser   = User::factory()->create();
        $sUser->assignRole('student');
        Student::factory()->create(['user_id' => $sUser->id, 'program_id' => $program->id]);

        $this->actingAs($sUser)->get(route('student.results'))->assertStatus(200);
    }
}
