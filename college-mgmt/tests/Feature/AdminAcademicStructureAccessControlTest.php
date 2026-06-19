<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAcademicStructureAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_global_academic_authority_roles_can_open_academic_structure_surfaces(): void
    {
        foreach (['admin', 'director', 'dean_academics'] as $role) {
            $user = $this->userWithRole($role);

            foreach ([
                'admin.departments.index',
                'admin.courses.index',
                'admin.programs.index',
                'admin.batches.index',
                'admin.subjects.index',
                'admin.academic-years.index',
                'admin.semesters.index',
            ] as $routeName) {
                $this->actingAs($user)->get(route($routeName))->assertOk();
            }
        }
    }

    public function test_scoped_or_non_structure_admin_group_roles_cannot_open_global_academic_structure_surfaces(): void
    {
        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            foreach ([
                'admin.departments.index',
                'admin.courses.index',
                'admin.programs.index',
                'admin.batches.index',
                'admin.subjects.index',
                'admin.academic-years.index',
                'admin.semesters.index',
            ] as $routeName) {
                $this->actingAs($user)->get(route($routeName))->assertForbidden();
            }
        }
    }

    public function test_scoped_roles_cannot_mutate_global_academic_structure_records_directly(): void
    {
        $chair = $this->userWithRole('program_chair');
        $fixture = $this->academicStructureFixture();

        $this->actingAs($chair)->post(route('admin.departments.store'), [
            'name' => 'Blocked Department',
            'code' => 'BDPT',
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.courses.store'), [
            'department_id' => $fixture['department']->id,
            'name' => 'Blocked Course',
            'code' => 'BCRS',
            'duration_years' => 2,
            'total_semesters' => 4,
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.programs.store'), [
            'department_id' => $fixture['department']->id,
            'name' => 'Blocked Program',
            'code' => 'BPRG',
            'system_type' => 'semester',
            'duration_years' => 2,
            'total_terms' => 4,
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.programs.specializations.store', $fixture['program']), [
            'name' => 'Blocked Specialization',
            'code' => 'BSPL',
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.batches.store'), [
            'program_id' => $fixture['program']->id,
            'name' => 'Blocked Batch',
            'code' => 'BBAT',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addYear()->toDateString(),
            'intake_capacity' => 60,
            'status' => 'upcoming',
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.terms.store'), [
            'batch_id' => $fixture['batch']->id,
            'term_number' => 2,
            'name' => 'Blocked Term',
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.subjects.store'), [
            'department_id' => $fixture['department']->id,
            'name' => 'Blocked Subject',
            'code' => 'BSUB',
            'credits' => 3,
            'type' => 'theory',
            'hours_per_week' => 3,
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.academic-years.store'), [
            'name' => 'Blocked Year',
            'start_year' => '2090',
            'end_year' => '2091',
            'start_date' => '2090-07-01',
            'end_date' => '2091-06-30',
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.semesters.store'), [
            'academic_year_id' => $fixture['academicYear']->id,
            'name' => 'Blocked Semester',
            'number' => 2,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addMonths(6)->toDateString(),
        ])->assertForbidden();

        $this->actingAs($chair)->patch(route('admin.terms.set-current', $fixture['term']))
            ->assertForbidden();

        $this->actingAs($chair)->delete(route('admin.specializations.destroy', $fixture['specialization']))
            ->assertForbidden();

        $this->assertDatabaseMissing('departments', ['code' => 'BDPT']);
        $this->assertDatabaseMissing('courses', ['code' => 'BCRS']);
        $this->assertDatabaseMissing('programs', ['code' => 'BPRG']);
        $this->assertDatabaseMissing('specializations', ['code' => 'BSPL']);
        $this->assertDatabaseMissing('batches', ['code' => 'BBAT']);
        $this->assertDatabaseMissing('terms', ['name' => 'Blocked Term']);
        $this->assertDatabaseMissing('subjects', ['code' => 'BSUB']);
        $this->assertDatabaseMissing('academic_years', ['name' => 'Blocked Year']);
        $this->assertDatabaseMissing('semesters', ['name' => 'Blocked Semester']);
        $this->assertDatabaseHas('specializations', ['id' => $fixture['specialization']->id]);
        $this->assertFalse((bool) $fixture['term']->fresh()->is_current);
    }

    private function academicStructureFixture(): array
    {
        $department = Department::factory()->create(['is_active' => true]);
        $course = Course::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $program = Program::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $specialization = Specialization::create([
            'program_id' => $program->id,
            'name' => 'Existing Specialization',
            'code' => 'EXSPL',
            'is_active' => true,
        ]);
        $academicYear = AcademicYear::factory()->create();
        $batch = Batch::factory()->create([
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
        ]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term I',
            'is_current' => false,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'is_active' => true,
        ]);
        $semester = Semester::factory()->create([
            'academic_year_id' => $academicYear->id,
            'number' => 1,
            'name' => 'Semester I',
            'is_current' => false,
        ]);

        return compact('department', 'course', 'program', 'specialization', 'academicYear', 'batch', 'term', 'subject', 'semester');
    }
}
