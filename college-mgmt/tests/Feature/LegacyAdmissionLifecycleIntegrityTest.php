<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Course;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyAdmissionLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function admission(array $overrides = []): Admission
    {
        $course = Course::factory()->create();

        return Admission::create(array_merge([
            'applicant_name' => 'Legacy Applicant',
            'email' => 'legacy-applicant@example.test',
            'phone' => '9876543210',
            'father_name' => 'Legacy Father',
            'mother_name' => 'Legacy Mother',
            'date_of_birth' => '2004-05-10',
            'gender' => 'female',
            'address' => 'Legacy admission address',
            'category' => 'general',
            'course_id' => $course->id,
            'last_qualification' => 'Class XII',
            'last_institution' => 'Demo School',
            'last_percentage' => 88.50,
            'application_date' => now()->toDateString(),
            'status' => 'shortlisted',
            'remarks' => 'Ready for conversion.',
        ], $overrides));
    }

    public function test_converted_legacy_admission_is_locked_from_reversal_deletion_and_repeat_conversion(): void
    {
        $admin = $this->admin();
        $admission = $this->admission();
        $department = Department::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.admissions.convert', $admission), [
                'enrollment_number' => 'LEGACY-ENR-001',
                'department_id' => $department->id,
            ])
            ->assertRedirect();

        $student = Student::firstOrFail();
        $admission->refresh();

        $this->assertSame('admitted', $admission->status);
        $this->assertSame($student->id, $admission->converted_student_id);

        $this->actingAs($admin)
            ->patch(route('admin.admissions.status', $admission), [
                'status' => 'withdrawn',
                'remarks' => 'Trying to reverse after student creation.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Converted admissions are locked. Use the student lifecycle or audited correction workflow instead of changing admission status.');

        $this->actingAs($admin)
            ->put(route('admin.admissions.update', $admission), [
                'applicant_name' => 'Rewritten Applicant',
                'email' => $admission->email,
                'phone' => $admission->phone,
                'father_name' => $admission->father_name,
                'mother_name' => $admission->mother_name,
                'date_of_birth' => $admission->date_of_birth->toDateString(),
                'gender' => $admission->gender,
                'address' => $admission->address,
                'category' => $admission->category,
                'course_id' => $admission->course_id,
                'last_qualification' => $admission->last_qualification,
                'last_institution' => $admission->last_institution,
                'last_percentage' => $admission->last_percentage,
                'application_date' => $admission->application_date->toDateString(),
                'status' => 'admitted',
                'remarks' => 'Trying to rewrite converted identity.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Converted admissions are locked. Use the student lifecycle or audited correction workflow instead of rewriting admission history.');

        $this->actingAs($admin)
            ->delete(route('admin.admissions.destroy', $admission))
            ->assertRedirect()
            ->assertSessionHas('error', 'Converted admissions cannot be deleted because linked student history depends on them.');

        $this->actingAs($admin)
            ->post(route('admin.admissions.convert', $admission), [
                'enrollment_number' => 'LEGACY-ENR-002',
                'department_id' => $department->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'This admission has already been converted to a student.');

        $admission->refresh();
        $this->assertSame('Legacy Applicant', $admission->applicant_name);
        $this->assertSame('admitted', $admission->status);
        $this->assertSame($student->id, $admission->converted_student_id);
        $this->assertDatabaseCount('admissions', 1);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseMissing('students', ['enrollment_number' => 'LEGACY-ENR-002']);
    }

    public function test_legacy_admission_create_and_update_require_active_course(): void
    {
        $admin = $this->admin();
        $activeCourse = Course::factory()->create(['is_active' => true]);
        $inactiveCourse = Course::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.admissions.create'))
            ->post(route('admin.admissions.store'), [
                'applicant_name' => 'Inactive Course Applicant',
                'email' => 'inactive-course-applicant@example.test',
                'phone' => '9876543211',
                'course_id' => $inactiveCourse->id,
                'status' => 'enquiry',
                'date_of_birth' => '2005-02-15',
                'last_percentage' => 76.25,
            ])
            ->assertRedirect(route('admin.admissions.create'))
            ->assertSessionHasErrors('course_id');

        $this->assertDatabaseMissing('admissions', [
            'email' => 'inactive-course-applicant@example.test',
        ]);

        $admission = $this->admission([
            'course_id' => $activeCourse->id,
            'email' => 'active-course-admission@example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.admissions.edit', $admission))
            ->put(route('admin.admissions.update', $admission), [
                'applicant_name' => $admission->applicant_name,
                'email' => $admission->email,
                'phone' => $admission->phone,
                'father_name' => $admission->father_name,
                'mother_name' => $admission->mother_name,
                'date_of_birth' => $admission->date_of_birth->toDateString(),
                'gender' => $admission->gender,
                'address' => $admission->address,
                'category' => $admission->category,
                'course_id' => $inactiveCourse->id,
                'last_qualification' => $admission->last_qualification,
                'last_institution' => $admission->last_institution,
                'last_percentage' => $admission->last_percentage,
                'application_date' => $admission->application_date->toDateString(),
                'status' => $admission->status,
                'remarks' => $admission->remarks,
            ])
            ->assertRedirect(route('admin.admissions.edit', $admission))
            ->assertSessionHasErrors('course_id');

        $this->assertSame($activeCourse->id, $admission->refresh()->course_id);
    }

    public function test_legacy_admission_conversion_requires_active_department(): void
    {
        $admin = $this->admin();
        $admission = $this->admission();
        $inactiveDepartment = Department::factory()->create(['is_active' => false]);
        $activeDepartment = Department::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.admissions.show', $admission))
            ->post(route('admin.admissions.convert', $admission), [
                'enrollment_number' => 'LEGACY-INACTIVE-DEPT',
                'department_id' => $inactiveDepartment->id,
            ])
            ->assertRedirect(route('admin.admissions.show', $admission))
            ->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('students', [
            'enrollment_number' => 'LEGACY-INACTIVE-DEPT',
        ]);
        $this->assertSame('shortlisted', $admission->refresh()->status);
        $this->assertNull($admission->converted_student_id);

        $this->actingAs($admin)
            ->post(route('admin.admissions.convert', $admission), [
                'enrollment_number' => 'LEGACY-ACTIVE-DEPT',
                'department_id' => $activeDepartment->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'enrollment_number' => 'LEGACY-ACTIVE-DEPT',
            'department_id' => $activeDepartment->id,
        ]);
        $this->assertSame('admitted', $admission->refresh()->status);
        $this->assertNotNull($admission->converted_student_id);
    }

    public function test_rejected_or_withdrawn_legacy_admissions_cannot_be_converted_to_students(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['is_active' => true]);

        foreach (['rejected', 'withdrawn'] as $status) {
            $admission = $this->admission([
                'email' => "{$status}-legacy-admission@example.test",
                'status' => $status,
            ]);

            $this->actingAs($admin)
                ->from(route('admin.admissions.show', $admission))
                ->post(route('admin.admissions.convert', $admission), [
                    'enrollment_number' => 'LEGACY-' . strtoupper($status),
                    'department_id' => $department->id,
                ])
                ->assertRedirect(route('admin.admissions.show', $admission))
                ->assertSessionHas('error', 'Rejected or withdrawn admissions cannot be converted to students. Reopen through an audited admission correction workflow first.');

            $admission->refresh();
            $this->assertSame($status, $admission->status);
            $this->assertNull($admission->converted_student_id);
            $this->assertDatabaseMissing('students', [
                'enrollment_number' => 'LEGACY-' . strtoupper($status),
            ]);
        }
    }

    public function test_legacy_admissions_cannot_be_marked_admitted_without_student_conversion(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create(['is_active' => true]);
        $message = 'Use Convert to Student to admit legacy admissions so a linked active student record is created.';

        $this->actingAs($admin)
            ->from(route('admin.admissions.create'))
            ->post(route('admin.admissions.store'), [
                'applicant_name' => 'Manual Admitted Applicant',
                'email' => 'manual-admitted-applicant@example.test',
                'phone' => '9876543221',
                'course_id' => $course->id,
                'status' => 'admitted',
                'date_of_birth' => '2005-02-15',
                'last_percentage' => 76.25,
            ])
            ->assertRedirect(route('admin.admissions.create'))
            ->assertSessionHasErrors(['status' => $message]);

        $this->assertDatabaseMissing('admissions', [
            'email' => 'manual-admitted-applicant@example.test',
        ]);

        $admission = $this->admission([
            'course_id' => $course->id,
            'email' => 'status-bypass-admission@example.test',
            'status' => 'shortlisted',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.admissions.edit', $admission))
            ->put(route('admin.admissions.update', $admission), [
                'applicant_name' => $admission->applicant_name,
                'email' => $admission->email,
                'phone' => $admission->phone,
                'father_name' => $admission->father_name,
                'mother_name' => $admission->mother_name,
                'date_of_birth' => $admission->date_of_birth->toDateString(),
                'gender' => $admission->gender,
                'address' => $admission->address,
                'category' => $admission->category,
                'course_id' => $admission->course_id,
                'last_qualification' => $admission->last_qualification,
                'last_institution' => $admission->last_institution,
                'last_percentage' => $admission->last_percentage,
                'application_date' => $admission->application_date->toDateString(),
                'status' => 'admitted',
                'remarks' => $admission->remarks,
            ])
            ->assertRedirect(route('admin.admissions.edit', $admission))
            ->assertSessionHasErrors(['status' => $message]);

        $this->assertSame('shortlisted', $admission->refresh()->status);
        $this->assertNull($admission->converted_student_id);

        $this->actingAs($admin)
            ->from(route('admin.admissions.show', $admission))
            ->patch(route('admin.admissions.status', $admission), [
                'status' => 'admitted',
                'remarks' => 'Trying direct admitted status.',
            ])
            ->assertRedirect(route('admin.admissions.show', $admission))
            ->assertSessionHasErrors(['status' => $message]);

        $this->assertSame('shortlisted', $admission->fresh()->status);
        $this->assertNull($admission->fresh()->converted_student_id);
        $this->assertDatabaseCount('students', 0);
    }
}
