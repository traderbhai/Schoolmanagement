<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAcademicMasterDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function academicSet(): array
    {
        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'intake_capacity' => 60]);
        $term = Term::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term I',
            'is_current' => true,
            'sort_order' => 1,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'credits' => 3,
            'type' => 'theory',
            'hours_per_week' => 3,
        ]);

        return compact('department', 'program', 'batch', 'term', 'subject');
    }

    public function test_program_with_academic_dependencies_cannot_be_deleted_or_structurally_changed(): void
    {
        $this->actingAs($this->admin());
        $set = $this->academicSet();

        $this->delete(route('admin.programs.destroy', $set['program']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('programs', ['id' => $set['program']->id]);

        $this->put(route('admin.programs.update', $set['program']), [
            'name' => $set['program']->name,
            'code' => $set['program']->code,
            'department_id' => $set['department']->id,
            'system_type' => 'trimester',
            'duration_years' => 2,
            'total_terms' => 6,
        ])->assertSessionHasErrors('program');

        $this->assertSame('semester', $set['program']->fresh()->system_type);
        $this->assertSame(4, $set['program']->fresh()->total_terms);
    }

    public function test_batch_with_students_cannot_be_deleted_cancelled_or_reduced_below_current_strength(): void
    {
        $this->actingAs($this->admin());
        $set = $this->academicSet();
        Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);

        $basePayload = [
            'name' => $set['batch']->name,
            'code' => $set['batch']->code,
            'start_date' => $set['batch']->start_date->toDateString(),
            'end_date' => $set['batch']->end_date->toDateString(),
        ];

        $this->put(route('admin.batches.update', $set['batch']), $basePayload + [
            'intake_capacity' => 0,
            'status' => 'active',
        ])->assertSessionHasErrors('intake_capacity');

        $this->put(route('admin.batches.update', $set['batch']), $basePayload + [
            'intake_capacity' => 60,
            'status' => 'cancelled',
        ])->assertSessionHasErrors('status');

        $this->delete(route('admin.batches.destroy', $set['batch']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('batches', ['id' => $set['batch']->id, 'status' => 'active']);
    }

    public function test_batch_creation_requires_active_program_and_dates_inside_academic_year(): void
    {
        $this->actingAs($this->admin());

        $year = AcademicYear::factory()->create([
            'name' => '2026-2027',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);
        $activeProgram = Program::factory()->create(['is_active' => true]);

        $this->post(route('admin.batches.store'), [
            'program_id' => $inactiveProgram->id,
            'academic_year_id' => $year->id,
            'name' => 'Inactive Program Batch',
            'code' => 'INACT-B',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'intake_capacity' => 60,
            'status' => 'upcoming',
        ])->assertSessionHasErrors('program_id');

        $this->post(route('admin.batches.store'), [
            'program_id' => $activeProgram->id,
            'academic_year_id' => $year->id,
            'name' => 'Out Of Year Batch',
            'code' => 'OUTYR-B',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'intake_capacity' => 60,
            'status' => 'upcoming',
        ])->assertSessionHasErrors('academic_year_id');

        $this->assertDatabaseMissing('batches', ['code' => 'INACT-B']);
        $this->assertDatabaseMissing('batches', ['code' => 'OUTYR-B']);
    }

    public function test_batch_with_operational_history_cannot_have_academic_window_rewritten(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $year = AcademicYear::factory()->create([
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);
        $set['batch']->update([
            'academic_year_id' => $year->id,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);

        $this->put(route('admin.batches.update', $set['batch']), [
            'academic_year_id' => $year->id,
            'name' => 'Rewritten Batch Dates',
            'code' => $set['batch']->code,
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'intake_capacity' => 60,
            'status' => 'active',
        ])->assertSessionHasErrors('batch');

        $set['batch']->refresh();
        $this->assertSame('2026-07-01', $set['batch']->start_date->toDateString());
        $this->assertSame('2027-06-30', $set['batch']->end_date->toDateString());
        $this->assertNotSame('Rewritten Batch Dates', $set['batch']->name);
    }

    public function test_term_with_enrollment_history_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $set = $this->academicSet();
        $student = Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $set['subject']->id,
            'term_id' => $set['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->delete(route('admin.terms.destroy', $set['term']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('terms', ['id' => $set['term']->id]);
    }

    public function test_term_update_ignores_direct_rebind_fields_and_locks_used_date_window(): void
    {
        $this->actingAs($this->admin());
        $set = $this->academicSet();
        $otherProgram = Program::factory()->create();
        $otherBatch = Batch::factory()->create(['program_id' => $otherProgram->id]);
        $student = Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $set['subject']->id,
            'term_id' => $set['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $set['term']->update([
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'sort_order' => 1,
        ]);

        $this->put(route('admin.terms.update', $set['term']), [
            'name' => 'Term I Updated',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'program_id' => $otherProgram->id,
            'batch_id' => $otherBatch->id,
            'term_number' => 9,
            'sort_order' => 99,
        ])->assertRedirect();

        $set['term']->refresh();
        $this->assertSame('Term I Updated', $set['term']->name);
        $this->assertSame($set['program']->id, $set['term']->program_id);
        $this->assertSame($set['batch']->id, $set['term']->batch_id);
        $this->assertSame(1, $set['term']->term_number);
        $this->assertSame(1, $set['term']->sort_order);

        $this->put(route('admin.terms.update', $set['term']), [
            'name' => 'Term I Rebased',
            'start_date' => '2026-08-01',
            'end_date' => '2027-01-31',
        ])->assertSessionHasErrors('term');

        $set['term']->refresh();
        $this->assertSame('Term I Updated', $set['term']->name);
        $this->assertSame('2026-07-01', $set['term']->start_date->toDateString());
        $this->assertSame('2026-12-31', $set['term']->end_date->toDateString());
    }

    public function test_term_creation_respects_program_batch_structure_and_duplicate_term_numbers(): void
    {
        $this->actingAs($this->admin());

        $program = Program::factory()->create(['is_active' => true, 'total_terms' => 2]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        Term::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term I',
        ]);

        $this->post(route('admin.terms.store'), [
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Duplicate Term I',
        ])->assertSessionHasErrors('term_number');

        $this->post(route('admin.terms.store'), [
            'batch_id' => $batch->id,
            'term_number' => 3,
            'name' => 'Term III',
        ])->assertSessionHasErrors('term_number');

        $inactiveProgram = Program::factory()->create(['is_active' => false, 'total_terms' => 2]);
        $inactiveProgramBatch = Batch::factory()->create([
            'program_id' => $inactiveProgram->id,
            'status' => 'active',
        ]);
        $this->post(route('admin.terms.store'), [
            'batch_id' => $inactiveProgramBatch->id,
            'term_number' => 1,
            'name' => 'Inactive Program Term',
        ])->assertSessionHasErrors('batch_id');

        $completedBatch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'completed']);
        $this->post(route('admin.terms.store'), [
            'batch_id' => $completedBatch->id,
            'term_number' => 1,
            'name' => 'Completed Batch Term',
        ])->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('terms', ['batch_id' => $batch->id, 'term_number' => 3]);
        $this->assertDatabaseMissing('terms', ['batch_id' => $inactiveProgramBatch->id]);
        $this->assertDatabaseMissing('terms', ['batch_id' => $completedBatch->id]);
    }

    public function test_current_term_cannot_be_set_for_closed_batch_or_inactive_program(): void
    {
        $this->actingAs($this->admin());

        $activeProgram = Program::factory()->create(['is_active' => true]);
        $completedBatch = Batch::factory()->create(['program_id' => $activeProgram->id, 'status' => 'completed']);
        $closedTerm = Term::create([
            'program_id' => $activeProgram->id,
            'batch_id' => $completedBatch->id,
            'term_number' => 1,
            'name' => 'Closed Batch Term',
            'is_current' => false,
        ]);

        $this->patch(route('admin.terms.set-current', $closedTerm))
            ->assertSessionHasErrors('term');

        $this->assertFalse((bool) $closedTerm->fresh()->is_current);

        $inactiveProgram = Program::factory()->create(['is_active' => false]);
        $activeBatch = Batch::factory()->create(['program_id' => $inactiveProgram->id, 'status' => 'active']);
        $inactiveProgramTerm = Term::create([
            'program_id' => $inactiveProgram->id,
            'batch_id' => $activeBatch->id,
            'term_number' => 1,
            'name' => 'Inactive Program Term',
            'is_current' => false,
        ]);

        $this->patch(route('admin.terms.set-current', $inactiveProgramTerm))
            ->assertSessionHasErrors('term');

        $this->assertFalse((bool) $inactiveProgramTerm->fresh()->is_current);
    }

    public function test_subject_with_academic_history_cannot_be_deleted_or_structurally_changed(): void
    {
        $this->actingAs($this->admin());
        $set = $this->academicSet();
        $student = Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $set['subject']->id,
            'term_id' => $set['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->delete(route('admin.subjects.destroy', $set['subject']))
            ->assertSessionHas('error');

        $this->put(route('admin.subjects.update', $set['subject']), [
            'department_id' => $set['department']->id,
            'name' => $set['subject']->name,
            'code' => $set['subject']->code,
            'description' => $set['subject']->description,
            'credits' => 4,
            'type' => 'theory',
            'hours_per_week' => 3,
            'is_active' => true,
        ])->assertSessionHasErrors('subject');

        $this->assertDatabaseHas('subjects', ['id' => $set['subject']->id, 'credits' => 3]);
    }

    public function test_department_with_operational_children_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $set = $this->academicSet();

        $this->delete(route('admin.departments.destroy', $set['department']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['id' => $set['department']->id]);
    }

    public function test_academic_year_with_operational_dependencies_cannot_be_deleted_or_rebounded(): void
    {
        $this->actingAs($this->admin());

        $year = AcademicYear::factory()->create([
            'name' => '2026-2027',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);
        Semester::factory()->create(['academic_year_id' => $year->id]);
        Batch::factory()->create(['academic_year_id' => $year->id]);
        $course = Course::factory()->create();
        FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'fee_type' => 'tuition',
            'amount' => 50000,
        ]);

        $this->delete(route('admin.academic-years.destroy', $year))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
        $this->assertDatabaseHas('semesters', ['academic_year_id' => $year->id]);
        $this->assertDatabaseHas('fee_structures', ['academic_year_id' => $year->id]);

        $this->put(route('admin.academic-years.update', $year), [
            'name' => 'AY 2026-27',
            'start_year' => 2026,
            'end_year' => 2028,
            'start_date' => '2026-06-01',
            'end_date' => '2028-05-31',
            'is_current' => true,
        ])->assertSessionHasErrors('academic_year');

        $year->refresh();
        $this->assertSame('2026-2027', $year->name);
        $this->assertSame('2026-07-01', $year->start_date->toDateString());
        $this->assertSame('2027-06-30', $year->end_date->toDateString());
    }

    public function test_academic_year_with_dependencies_can_still_be_renamed_and_marked_current(): void
    {
        $this->actingAs($this->admin());

        $current = AcademicYear::factory()->create(['is_current' => true]);
        $year = AcademicYear::factory()->create([
            'name' => '2027-2028',
            'start_year' => 2027,
            'end_year' => 2028,
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'is_current' => false,
        ]);
        Semester::factory()->create(['academic_year_id' => $year->id]);

        $this->put(route('admin.academic-years.update', $year), [
            'name' => 'Academic Year 2027-28',
            'start_year' => 2027,
            'end_year' => 2028,
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'is_current' => true,
        ])->assertRedirect(route('admin.academic-years.index'));

        $this->assertFalse($current->fresh()->is_current);
        $this->assertTrue($year->fresh()->is_current);
        $this->assertSame('Academic Year 2027-28', $year->fresh()->name);
    }

    public function test_semester_with_academic_history_cannot_be_deleted_or_rebounded(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $year = AcademicYear::factory()->create();
        $otherYear = AcademicYear::factory()->create();
        $semester = Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
        ]);
        $student = Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $set['subject']->id,
            'status' => 'active',
        ]);

        $this->delete(route('admin.semesters.destroy', $semester))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);
        $this->assertDatabaseHas('enrollments', ['semester_id' => $semester->id]);

        $this->put(route('admin.semesters.update', $semester), [
            'academic_year_id' => $otherYear->id,
            'name' => 'Rebounded Semester',
            'number' => 2,
            'start_date' => '2026-08-01',
            'end_date' => '2027-01-31',
            'is_current' => true,
        ])->assertSessionHasErrors('semester');

        $semester->refresh();
        $this->assertSame($year->id, $semester->academic_year_id);
        $this->assertSame(1, $semester->number);
        $this->assertSame('2026-07-01', $semester->start_date->toDateString());
        $this->assertSame('2026-12-31', $semester->end_date->toDateString());
    }

    public function test_semester_creation_requires_unique_number_and_dates_inside_academic_year(): void
    {
        $this->actingAs($this->admin());

        $year = AcademicYear::factory()->create([
            'name' => '2026-2027',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);
        Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
        ]);

        $this->post(route('admin.semesters.store'), [
            'academic_year_id' => $year->id,
            'name' => 'Duplicate Semester I',
            'number' => 1,
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
        ])->assertSessionHasErrors('semester');

        $this->post(route('admin.semesters.store'), [
            'academic_year_id' => $year->id,
            'name' => 'Out Of Range Semester',
            'number' => 2,
            'start_date' => '2027-07-01',
            'end_date' => '2027-12-31',
        ])->assertSessionHasErrors('semester');

        $this->assertDatabaseMissing('semesters', ['name' => 'Duplicate Semester I']);
        $this->assertDatabaseMissing('semesters', ['name' => 'Out Of Range Semester']);
    }

    public function test_semester_update_requires_unique_number_and_dates_inside_academic_year(): void
    {
        $this->actingAs($this->admin());

        $year = AcademicYear::factory()->create([
            'name' => '2027-2028',
            'start_year' => 2027,
            'end_year' => 2028,
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
        ]);
        Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semester I',
            'start_date' => '2027-07-01',
            'end_date' => '2027-12-31',
        ]);
        $semester = Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 2,
            'name' => 'Semester II',
            'start_date' => '2028-01-01',
            'end_date' => '2028-06-30',
        ]);

        $this->put(route('admin.semesters.update', $semester), [
            'academic_year_id' => $year->id,
            'name' => 'Semester II Duplicate Attempt',
            'number' => 1,
            'start_date' => '2028-01-01',
            'end_date' => '2028-06-30',
        ])->assertSessionHasErrors('semester');

        $this->put(route('admin.semesters.update', $semester), [
            'academic_year_id' => $year->id,
            'name' => 'Semester II Out Of Range Attempt',
            'number' => 2,
            'start_date' => '2028-01-01',
            'end_date' => '2028-07-31',
        ])->assertSessionHasErrors('semester');

        $semester->refresh();
        $this->assertSame('Semester II', $semester->name);
        $this->assertSame(2, $semester->number);
        $this->assertSame('2028-06-30', $semester->end_date->toDateString());
    }

    public function test_semester_with_history_can_still_be_renamed_and_marked_current(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $current = Semester::factory()->create(['is_current' => true]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Semester One',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_current' => false,
        ]);
        $student = Student::factory()->create([
            'department_id' => $set['department']->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $set['subject']->id,
            'status' => 'active',
        ]);

        $this->put(route('admin.semesters.update', $semester), [
            'academic_year_id' => $semester->academic_year_id,
            'name' => 'Semester I',
            'number' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ])->assertRedirect(route('admin.semesters.index'));

        $this->assertFalse($current->fresh()->is_current);
        $this->assertTrue($semester->fresh()->is_current);
        $this->assertSame('Semester I', $semester->fresh()->name);
    }

    public function test_course_with_operational_dependencies_cannot_be_deleted_or_structurally_changed(): void
    {
        $this->actingAs($this->admin());

        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'code' => 'LEGACY-BBA',
            'duration_years' => 3,
            'total_semesters' => 6,
            'is_active' => true,
        ]);
        Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'tuition',
            'amount' => 45000,
        ]);

        $this->delete(route('admin.courses.destroy', $course))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('students', ['course_id' => $course->id]);
        $this->assertDatabaseHas('fee_structures', ['course_id' => $course->id]);

        $this->put(route('admin.courses.update', $course), [
            'department_id' => $otherDepartment->id,
            'name' => 'Changed Legacy BBA',
            'code' => 'LEGACY-BBA-X',
            'description' => 'Trying to restructure a live course.',
            'duration_years' => 4,
            'total_semesters' => 8,
            'is_active' => true,
        ])->assertSessionHasErrors('course');

        $course->refresh();
        $this->assertSame($department->id, $course->department_id);
        $this->assertSame('LEGACY-BBA', $course->code);
        $this->assertSame(3, $course->duration_years);
        $this->assertSame(6, $course->total_semesters);
    }

    public function test_course_with_active_students_cannot_be_deactivated_but_can_be_renamed(): void
    {
        $this->actingAs($this->admin());

        $department = Department::factory()->create();
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'name' => 'Legacy BCA',
            'code' => 'LEGACY-BCA',
            'duration_years' => 3,
            'total_semesters' => 6,
            'is_active' => true,
        ]);
        Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->put(route('admin.courses.update', $course), [
            'department_id' => $department->id,
            'name' => 'Legacy BCA Updated',
            'code' => 'LEGACY-BCA',
            'description' => 'Safe label update.',
            'duration_years' => 3,
            'total_semesters' => 6,
            'is_active' => true,
        ])->assertRedirect(route('admin.courses.index'));

        $this->assertSame('Legacy BCA Updated', $course->fresh()->name);

        $this->put(route('admin.courses.update', $course), [
            'department_id' => $department->id,
            'name' => 'Legacy BCA Updated',
            'code' => 'LEGACY-BCA',
            'description' => 'Trying to hide active course.',
            'duration_years' => 3,
            'total_semesters' => 6,
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $course->fresh()->is_active);
    }

    public function test_classroom_with_timetable_history_cannot_be_deleted_or_structurally_changed(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $set['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $set['department']->id]);
        $slot = TimetableSlot::factory()->create();
        $room = Classroom::factory()->create([
            'room_number' => 'ROOM-LOCK-101',
            'capacity' => 80,
            'type' => 'lecture',
            'building' => 'Academic Block',
            'floor' => '1',
            'has_projector' => true,
            'has_lab' => false,
            'is_active' => true,
        ]);
        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);

        $this->delete(route('admin.classrooms.destroy', $room))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('classrooms', ['id' => $room->id]);
        $this->assertDatabaseHas('timetable_entries', ['classroom_id' => $room->id]);

        $this->put(route('admin.classrooms.update', $room), [
            'name' => 'Room Lock 101',
            'room_number' => 'ROOM-LOCK-999',
            'capacity' => 80,
            'type' => 'lab',
            'building' => 'New Block',
            'floor' => '2',
            'has_projector' => false,
            'has_lab' => true,
            'is_active' => true,
        ])->assertSessionHasErrors('classroom');

        $room->refresh();
        $this->assertSame('ROOM-LOCK-101', $room->room_number);
        $this->assertSame('lecture', $room->type);
        $this->assertSame('Academic Block', $room->building);
    }

    public function test_classroom_with_active_schedule_cannot_be_deactivated_or_reduced_below_batch_need(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $set['batch']->update(['intake_capacity' => 60]);
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $set['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $set['department']->id]);
        $slot = TimetableSlot::factory()->create();
        $room = Classroom::factory()->create([
            'room_number' => 'ROOM-CAP-101',
            'capacity' => 80,
            'type' => 'lecture',
            'is_active' => true,
        ]);
        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 2,
            'is_active' => true,
        ]);

        $this->put(route('admin.classrooms.update', $room), [
            'name' => $room->name,
            'room_number' => $room->room_number,
            'capacity' => 40,
            'type' => 'lecture',
            'building' => $room->building,
            'floor' => $room->floor,
            'has_projector' => false,
            'has_lab' => false,
            'is_active' => true,
        ])->assertSessionHasErrors('capacity');

        $this->put(route('admin.classrooms.update', $room), [
            'name' => $room->name,
            'room_number' => $room->room_number,
            'capacity' => 80,
            'type' => 'lecture',
            'building' => $room->building,
            'floor' => $room->floor,
            'has_projector' => false,
            'has_lab' => false,
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');

        $this->assertSame(80, $room->fresh()->capacity);
        $this->assertTrue((bool) $room->fresh()->is_active);
    }

    public function test_timetable_slot_with_schedule_history_cannot_be_deleted_or_reshaped(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $set['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $set['department']->id]);
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create([
            'name' => 'Protected Period',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_break' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 3,
            'is_active' => true,
        ]);

        $this->delete(route('admin.timetable-slots.destroy', $slot))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id]);
        $this->assertDatabaseHas('timetable_entries', ['timetable_slot_id' => $slot->id]);

        $this->put(route('admin.timetable-slots.update', $slot), [
            'name' => 'Protected Period',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'is_break' => false,
            'sort_order' => 1,
            'is_active' => true,
        ])->assertSessionHasErrors('timetable_slot');

        $this->put(route('admin.timetable-slots.update', $slot), [
            'name' => 'Protected Period',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_break' => true,
            'sort_order' => 1,
            'is_active' => true,
        ])->assertSessionHasErrors('timetable_slot');

        $slot->refresh();
        $this->assertSame('09:00', substr($slot->start_time, 0, 5));
        $this->assertSame('10:00', substr($slot->end_time, 0, 5));
        $this->assertFalse((bool) $slot->is_break);
    }

    public function test_used_timetable_slot_can_be_relabelled_but_not_deactivated(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $set['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $set['department']->id]);
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create([
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_break' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 4,
            'is_active' => true,
        ]);

        $this->put(route('admin.timetable-slots.update', $slot), [
            'name' => 'Morning Period 1',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_break' => false,
            'sort_order' => 2,
            'is_active' => true,
        ])->assertRedirect(route('admin.timetable-slots.index'));

        $this->assertSame('Morning Period 1', $slot->fresh()->name);
        $this->assertSame(2, $slot->fresh()->sort_order);

        $this->put(route('admin.timetable-slots.update', $slot), [
            'name' => 'Morning Period 1',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_break' => false,
            'sort_order' => 2,
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $slot->fresh()->is_active);
    }

    public function test_timetable_entry_with_attendance_history_is_archived_not_deleted_or_restructured(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $set['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $set['department']->id]);
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create();
        $student = Student::factory()->create(['program_id' => $set['program']->id, 'course_id' => $course->id]);
        $entry = TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 5,
            'is_active' => true,
            'status' => 'published',
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
        ]);

        $this->put(route('admin.timetable.update', $entry), [
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
        ])->assertRedirect(route('admin.timetable.show', $entry))
            ->assertSessionHas('error', 'Timetable entries with attendance, substitution, or published history cannot be structurally changed. Create a revision instead.');

        $entry->refresh();
        $this->assertSame(5, $entry->day_of_week);
        $this->assertTrue((bool) $entry->is_active);
        $this->assertSame('published', $entry->status);

        $this->delete(route('admin.timetable.destroy', $entry))
            ->assertSessionHas('error', 'Timetable entry has attendance, substitution, or published history and was archived instead of deleted.');

        $this->assertDatabaseHas('timetable_entries', [
            'id' => $entry->id,
            'is_active' => false,
            'status' => 'archived',
        ]);
        $this->assertDatabaseHas('attendances', [
            'timetable_entry_id' => $entry->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_empty_draft_timetable_entry_can_still_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $set = $this->academicSet();
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $set['department']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $set['department']->id]);
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create();
        $entry = TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 6,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $this->delete(route('admin.timetable.destroy', $entry))
            ->assertSessionHas('success', 'Entry removed from timetable.');

        $this->assertDatabaseMissing('timetable_entries', ['id' => $entry->id]);
    }

    public function test_academic_master_data_cannot_be_created_under_inactive_departments(): void
    {
        $this->actingAs($this->admin());

        $inactiveDepartment = Department::factory()->create(['is_active' => false]);

        $this->post(route('admin.programs.store'), [
            'name' => 'Archived Department Program',
            'code' => 'ARCH-PROG',
            'department_id' => $inactiveDepartment->id,
            'system_type' => 'semester',
            'duration_years' => 2,
            'total_terms' => 4,
        ])->assertSessionHasErrors('department_id');

        $this->post(route('admin.courses.store'), [
            'department_id' => $inactiveDepartment->id,
            'name' => 'Archived Department Course',
            'code' => 'ARCH-COURSE',
            'description' => 'Should not attach to inactive department.',
            'duration_years' => 3,
            'total_semesters' => 6,
        ])->assertSessionHasErrors('department_id');

        $this->post(route('admin.subjects.store'), [
            'department_id' => $inactiveDepartment->id,
            'name' => 'Archived Department Subject',
            'code' => 'ARCH-SUBJ',
            'description' => 'Should not attach to inactive department.',
            'credits' => 3,
            'type' => 'theory',
            'hours_per_week' => 3,
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('programs', ['code' => 'ARCH-PROG']);
        $this->assertDatabaseMissing('courses', ['code' => 'ARCH-COURSE']);
        $this->assertDatabaseMissing('subjects', ['code' => 'ARCH-SUBJ']);
    }

    public function test_academic_master_data_cannot_be_moved_to_inactive_departments(): void
    {
        $this->actingAs($this->admin());

        $activeDepartment = Department::factory()->create(['is_active' => true]);
        $inactiveDepartment = Department::factory()->create(['is_active' => false]);
        $program = Program::factory()->create(['department_id' => $activeDepartment->id]);
        $course = Course::factory()->create(['department_id' => $activeDepartment->id]);
        $subject = Subject::factory()->create([
            'department_id' => $activeDepartment->id,
            'credits' => 3,
            'type' => 'theory',
            'hours_per_week' => 3,
        ]);

        $this->put(route('admin.programs.update', $program), [
            'name' => $program->name,
            'code' => $program->code,
            'department_id' => $inactiveDepartment->id,
            'system_type' => $program->system_type,
            'duration_years' => $program->duration_years,
            'total_terms' => $program->total_terms,
        ])->assertSessionHasErrors('department_id');

        $this->put(route('admin.courses.update', $course), [
            'department_id' => $inactiveDepartment->id,
            'name' => $course->name,
            'code' => $course->code,
            'description' => $course->description,
            'duration_years' => $course->duration_years,
            'total_semesters' => $course->total_semesters,
            'is_active' => true,
        ])->assertSessionHasErrors('department_id');

        $this->put(route('admin.subjects.update', $subject), [
            'department_id' => $inactiveDepartment->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'credits' => $subject->credits,
            'type' => $subject->type,
            'hours_per_week' => $subject->hours_per_week,
            'is_active' => true,
        ])->assertSessionHasErrors('department_id');

        $this->assertSame($activeDepartment->id, $program->fresh()->department_id);
        $this->assertSame($activeDepartment->id, $course->fresh()->department_id);
        $this->assertSame($activeDepartment->id, $subject->fresh()->department_id);
    }

    public function test_department_with_active_academic_children_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin());

        $department = Department::factory()->create(['is_active' => true]);
        Program::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        Course::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'is_active' => true]);

        $this->put(route('admin.departments.update', $department), [
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'head_name' => $department->head_name,
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $department->fresh()->is_active);
    }

    public function test_empty_master_data_can_still_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term I',
        ]);
        $subject = Subject::factory()->create(['department_id' => $department->id]);

        $this->delete(route('admin.terms.destroy', $term))->assertSessionHas('success');
        $this->delete(route('admin.subjects.destroy', $subject))->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseMissing('terms', ['id' => $term->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }
}
