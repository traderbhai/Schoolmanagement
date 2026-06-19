<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEnrollmentAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_global_academic_authority_roles_can_open_admin_enrollment_surfaces(): void
    {
        foreach (['admin', 'director', 'dean_academics'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.enrollments.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.enrollments.create'))->assertOk();
        }
    }

    public function test_broad_non_enrollment_admin_group_roles_cannot_open_global_enrollment_surfaces(): void
    {
        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.enrollments.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.enrollments.create'))->assertForbidden();
        }
    }

    public function test_broad_non_enrollment_admin_group_roles_cannot_mutate_global_enrollment_records(): void
    {
        $chair = $this->userWithRole('program_chair');
        $fixture = $this->academicFixture();
        $enrollment = Enrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'active',
        ]);

        $this->actingAs($chair)->post(route('admin.enrollments.store'), [
            'student_id' => $fixture['student']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_ids' => [$fixture['subject']->id],
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.enrollments.bulk'), [
            'course_id' => $fixture['course']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_ids' => [$fixture['subject']->id],
        ])->assertForbidden();

        $this->actingAs($chair)->delete(route('admin.enrollments.destroy', $enrollment))
            ->assertForbidden();

        $this->assertSame(1, Enrollment::where('student_id', $fixture['student']->id)->where('subject_id', $fixture['subject']->id)->count());
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'active']);
    }

    private function academicFixture(): array
    {
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['is_active' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'is_active' => true,
        ]);

        return compact('program', 'batch', 'course', 'term', 'semester', 'student', 'subject');
    }
}
