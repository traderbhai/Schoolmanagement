<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentApiResultsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_api_results_return_only_published_results(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $program = Program::factory()->create();
        $semester = Semester::factory()->create(['name' => 'Term 1', 'number' => 1]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $officialSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Published API Subject']);
        $draftSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Draft API Subject']);
        $officialExam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'subject_id' => $officialSubject->id,
            'published_at' => now(),
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'subject_id' => $draftSubject->id,
            'published_at' => null,
        ]);

        ExamResult::factory()->create([
            'exam_id' => $officialExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 81,
            'is_absent' => false,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 99,
            'is_absent' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/results')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'subject' => 'Published API Subject',
                'marks_obtained' => '81.00',
            ])
            ->assertJsonMissing([
                'subject' => 'Draft API Subject',
            ])
            ->assertJsonMissing([
                'marks_obtained' => '99.00',
            ]);
    }
}
