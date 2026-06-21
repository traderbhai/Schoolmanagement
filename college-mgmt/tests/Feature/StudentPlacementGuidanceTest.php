<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentPlacementGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Placement Student']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create(['name' => 'Placement Program'])->id,
            'status' => 'active',
        ]);
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Campus Recruiter',
            'industry' => 'Technology',
            'is_active' => true,
        ]);
    }

    private function drive(array $extra = []): PlacementDrive
    {
        return PlacementDrive::create(array_merge([
            'company_id' => $this->company()->id,
            'title' => 'Associate Consultant Drive',
            'job_role' => 'Associate Consultant',
            'package' => '6 LPA',
            'eligibility' => 'No active backlogs.',
            'drive_date' => now()->addWeek()->toDateString(),
            'last_apply_date' => now()->addDays(2)->toDateString(),
            'location' => 'Campus',
            'status' => 'upcoming',
            'vacancies' => 12,
        ], $extra));
    }

    private function resultForCgpa(Student $student, float $marks): void
    {
        $subject = Subject::factory()->create(['program_id' => $student->program_id]);
        $exam = Exam::factory()->create([
            'program_id' => $student->program_id,
            'subject_id' => $subject->id,
            'total_marks' => 10,
            'published_at' => now(),
        ]);
        ExamResult::factory()->create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'marks_obtained' => $marks,
            'is_absent' => false,
        ]);
    }

    public function test_student_placement_page_surfaces_deadline_priority_and_next_action(): void
    {
        $student = $this->student();
        $this->drive();

        $this->actingAs($student->user)
            ->get(route('student.placements'))
            ->assertStatus(200)
            ->assertSee('Placement Priority')
            ->assertSee('Placement deadline is near')
            ->assertSee('Associate Consultant Drive')
            ->assertSee('Apply by:')
            ->assertSee('No active backlogs.')
            ->assertSee('Apply Now');
    }

    public function test_student_placement_empty_states_explain_cmc_drive_and_application_workflow(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.placements'))
            ->assertOk()
            ->assertSee('No placement drives are open right now')
            ->assertSee('CMC publishes drives after company details, eligibility, application deadline, and drive date are confirmed')
            ->assertSee('use My Applications to track submitted applications')
            ->assertSee('No placement applications submitted yet')
            ->assertSee('Applications appear here after you apply to an open drive')
            ->assertSee('CMC updates shortlist, interview, selected, rejected, and joining-status outcomes')
            ->assertDontSee('No placement drives are open right now.')
            ->assertDontSee('You have not applied to any placement drives yet.');
    }

    public function test_student_cannot_apply_to_completed_or_expired_drive(): void
    {
        $student = $this->student();
        $completedDrive = $this->drive(['status' => 'completed']);
        $expiredDrive = $this->drive([
            'status' => 'ongoing',
            'last_apply_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $completedDrive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'This placement drive is not open for applications.');

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $expiredDrive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'The application deadline for this drive has passed.');

        $this->assertSame(0, Placement::where('student_id', $student->id)->count());
    }

    public function test_student_cannot_apply_when_minimum_cgpa_is_not_met(): void
    {
        $student = $this->student();
        $this->resultForCgpa($student, 6.25);
        $drive = $this->drive([
            'status' => 'ongoing',
            'min_cgpa' => 7.5,
            'title' => 'High CGPA Consulting Drive',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.placements'))
            ->assertOk()
            ->assertSee('High CGPA Consulting Drive')
            ->assertSee('Required CGPA 7.50; your CGPA 6.25.')
            ->assertSee('You do not meet the minimum CGPA requirement for this drive.');

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $drive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'You do not meet the minimum CGPA requirement for this drive.');

        $this->assertDatabaseMissing('placements', [
            'student_id' => $student->id,
            'drive_id' => $drive->id,
        ]);
    }

    public function test_draft_unpublished_marks_do_not_make_student_placement_eligible(): void
    {
        $student = $this->student();
        $this->resultForCgpa($student, 6.25);

        $draftSubject = Subject::factory()->create(['program_id' => $student->program_id]);
        $draftExam = Exam::factory()->create([
            'program_id' => $student->program_id,
            'subject_id' => $draftSubject->id,
            'total_marks' => 10,
            'published_at' => null,
        ]);
        ExamResult::factory()->create([
            'student_id' => $student->id,
            'exam_id' => $draftExam->id,
            'marks_obtained' => 9.9,
            'is_absent' => false,
        ]);

        $drive = $this->drive([
            'status' => 'ongoing',
            'min_cgpa' => 7.5,
            'title' => 'Draft Should Not Qualify Drive',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.placements'))
            ->assertOk()
            ->assertSee('Draft Should Not Qualify Drive')
            ->assertSee('Required CGPA 7.50; your CGPA 6.25.')
            ->assertSee('You do not meet the minimum CGPA requirement for this drive.');

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $drive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'You do not meet the minimum CGPA requirement for this drive.');

        $this->assertDatabaseMissing('placements', [
            'student_id' => $student->id,
            'drive_id' => $drive->id,
        ]);
    }

    public function test_student_can_apply_when_minimum_cgpa_is_met(): void
    {
        $student = $this->student();
        $this->resultForCgpa($student, 8.2);
        $drive = $this->drive([
            'status' => 'ongoing',
            'min_cgpa' => 7.5,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $drive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('success', 'Application submitted successfully.');

        $this->assertDatabaseHas('placements', [
            'student_id' => $student->id,
            'drive_id' => $drive->id,
            'application_status' => 'applied',
        ]);
    }

    public function test_inactive_student_can_review_history_but_cannot_apply_to_new_drive(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);

        $historyDrive = $this->drive([
            'title' => 'Historical Placement Drive',
            'status' => 'completed',
        ]);
        Placement::create([
            'drive_id' => $historyDrive->id,
            'student_id' => $student->id,
            'application_status' => 'interview',
            'offered_package' => 7.25,
        ]);

        $openDrive = $this->drive([
            'title' => 'Inactive Student Should Not Apply Drive',
            'status' => 'ongoing',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.placements'))
            ->assertOk()
            ->assertSee('Inactive Student Should Not Apply Drive')
            ->assertSee('Your student profile is not active, so you can only review existing placement history.')
            ->assertSee('Active students only')
            ->assertDontSee('Apply Now');

        $this->actingAs($student->user)
            ->get(route('student.placements.applications'))
            ->assertOk()
            ->assertSee('Historical Placement Drive')
            ->assertSee('Interview');

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $openDrive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'Placement applications are available only for active students.');

        $this->assertDatabaseMissing('placements', [
            'student_id' => $student->id,
            'drive_id' => $openDrive->id,
        ]);
    }

    public function test_student_application_tracking_explains_next_step_and_package(): void
    {
        $student = $this->student();
        $drive = $this->drive(['status' => 'ongoing']);

        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'interview',
            'offered_package' => 7.25,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.placements.applications'))
            ->assertStatus(200)
            ->assertSee('My Placement Applications')
            ->assertSee('Interview')
            ->assertSee('7.3 LPA')
            ->assertSee('Watch for interview schedule and instructions.');
    }

    public function test_student_application_tracking_uses_readable_package_fallback(): void
    {
        $student = $this->student();
        $drive = $this->drive([
            'status' => 'ongoing',
            'title' => 'Fallback Placement Drive',
            'job_role' => 'Analyst Trainee',
            'package' => null,
        ]);

        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.placements.applications'))
            ->assertOk()
            ->assertSee('Fallback Placement Drive')
            ->assertSee('Analyst Trainee')
            ->assertSee('Package pending')
            ->assertDontSee('<td>-</td>', false);
    }
}
