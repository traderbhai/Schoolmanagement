<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AcademicCourseDeliveryService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsCourseDeliveryV006Test extends TestCase
{
    use RefreshDatabase;

    private function seedCourseFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Riya Sharma']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'student');
    }

    public function test_faculty_can_open_course_delivery_dashboard(): void
    {
        $this->seedCourseFixture();
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.index'))
            ->assertOk()
            ->assertSee('Course Delivery OS')
            ->assertSee('Course Load')
            ->assertSee('Session Delivery')
            ->assertSee('Attendance Interventions')
            ->assertSee('Course Engagement')
            ->assertSee('Mentor Actions');
    }

    public function test_course_delivery_source_lists_are_database_backed(): void
    {
        $this->seedCourseFixture();
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.course-engagement'))
            ->assertOk()
            ->assertSee('Course Engagement')
            ->assertSee('Filtered Source List')
            ->assertSee('Course Delivery Lab Prep')
            ->assertSee('Clarify attendance recovery task');

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.attendance-interventions'))
            ->assertOk()
            ->assertSee('Attendance Interventions')
            ->assertSee('Riya Sharma');
    }

    public function test_course_delivery_service_respects_faculty_assignment_scope(): void
    {
        $this->seedCourseFixture();
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();
        $otherUser = User::factory()->create(['name' => 'Other Faculty']);
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherUser->id]);
        $hiddenSubject = Subject::factory()->create(['name' => 'Hidden Delivery Subject', 'is_active' => true]);
        SubjectFacultyAssignment::create([
            'subject_id' => $hiddenSubject->id,
            'teacher_id' => $otherTeacher->id,
            'term_id' => \App\Models\Term::query()->value('id'),
            'program_id' => $hiddenSubject->program_id,
            'assigned_by' => $faculty->id,
            'is_primary' => true,
        ]);

        $items = app(AcademicCourseDeliveryService::class)->courseLoad($faculty)['items'];
        $titles = collect($items)->pluck('title');

        $this->assertFalse($titles->contains('Hidden Delivery Subject'));
    }

    public function test_non_academic_user_cannot_access_course_delivery_os(): void
    {
        $this->seedCourseFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.course-delivery.index'))
            ->assertForbidden();
    }
}
