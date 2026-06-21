<?php

namespace Tests\Feature;

use App\Models\TermPromotion;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TermPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function eligiblePendingPromotion(): TermPromotion
    {
        $program = Program::factory()->create();
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
        ]);
        $currentTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
        ]);
        $promotedToTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 2,
        ]);
        $student->update(['current_term_id' => $currentTerm->id]);

        return TermPromotion::factory()->create([
            'student_id' => $student->id,
            'current_term_id' => $currentTerm->id,
            'promoted_to_term_id' => $promotedToTerm->id,
            'meets_academic_criteria' => true,
            'meets_attendance_criteria' => true,
            'status' => 'pending',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('admin'));
    }

    public function test_can_view_promotions_list()
    {
        TermPromotion::factory()->count(5)->create();

        $response = $this->get('/academic/term-promotions');

        $response->assertStatus(200);
    }

    public function test_can_view_promotion_details()
    {
        $promotion = TermPromotion::factory()->create();

        $response = $this->get("/academic/term-promotions/{$promotion->id}");

        $response->assertStatus(200);
    }

    public function test_term_promotion_pages_use_readable_progression_fallbacks(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $student = Student::factory()->make(['enrollment_number' => null]);
        $student->setRelation('user', null);

        $promotion = TermPromotion::factory()->make([
            'cgpa' => 2.75,
            'attendance_percentage' => 72.5,
            'meets_academic_criteria' => true,
            'meets_attendance_criteria' => false,
            'status' => 'pending',
            'remarks' => null,
        ]);
        $promotion->id = 4321;
        $promotion->setRelation('student', $student);
        $promotion->setRelation('currentTerm', null);
        $promotion->setRelation('promotedToTerm', null);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(collect([$promotion]), 1, 15);

        $listHtml = view('academic.term-promotions.index', [
            'promotions' => $paginator,
        ])->render();

        $showHtml = view('academic.term-promotions.show', [
            'termPromotion' => $promotion,
        ])->render();

        $editHtml = view('academic.term-promotions.edit', [
            'termPromotion' => $promotion,
        ])->render();

        foreach ([$listHtml, $showHtml, $editHtml] as $html) {
            $this->assertStringNotContainsString('N/A', $html);
            $this->assertStringNotContainsString('â', $html);
            $this->assertStringNotContainsString('&mdash;', $html);
            $this->assertStringNotContainsString('&ndash;', $html);
        }

        $this->assertStringContainsString('Student name missing', $listHtml);
        $this->assertStringContainsString('Current term not linked', $listHtml);
        $this->assertStringContainsString('Target term not linked', $listHtml);
        $this->assertStringContainsString('Student name missing', $showHtml);
        $this->assertStringContainsString('No reviewer remarks yet', $showHtml);
        $this->assertStringContainsString('Enrollment number pending', $editHtml);
        $this->assertStringContainsString('Current term not linked', $editHtml);
    }

    public function test_can_approve_eligible_promotion()
    {
        $promotion = $this->eligiblePendingPromotion();

        $response = $this->post("/academic/term-promotions/{$promotion->id}/approve");

        $this->assertEquals('approved', $promotion->fresh()->status);
        $this->assertEquals($promotion->promoted_to_term_id, $promotion->student->fresh()->current_term_id);
    }

    public function test_accounts_user_cannot_directly_mutate_term_promotions(): void
    {
        $promotion = $this->eligiblePendingPromotion();
        $accounts = $this->userWithRole('accounts_officer');

        $this->actingAs($accounts)
            ->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertForbidden();

        $this->actingAs($accounts)
            ->post("/academic/term-promotions/{$promotion->id}/reject", [
                'remarks' => 'Not my academic decision.',
            ])
            ->assertForbidden();

        $this->actingAs($accounts)
            ->put("/academic/term-promotions/{$promotion->id}", [
                'status' => 'on_hold',
                'remarks' => 'Unauthorized hold.',
            ])
            ->assertForbidden();

        $this->assertEquals('pending', $promotion->fresh()->status);
    }

    public function test_cannot_approve_ineligible_promotion()
    {
        $promotion = TermPromotion::factory()->create([
            'meets_academic_criteria' => false,
            'meets_attendance_criteria' => true,
            'status' => 'pending',
        ]);

        $response = $this->post("/academic/term-promotions/{$promotion->id}/approve");

        $this->assertEquals('pending', $promotion->fresh()->status);
    }

    public function test_can_reject_promotion()
    {
        $promotion = TermPromotion::factory()->create(['status' => 'pending']);

        $response = $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => 'Does not meet criteria',
        ]);

        $this->assertEquals('rejected', $promotion->fresh()->status);
    }

    public function test_promotion_update_student_term()
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);
        $currentTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
        ]);
        $promotedToTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 2,
        ]);

        $student->update(['current_term_id' => $currentTerm->id]);

        $promotion = TermPromotion::factory()->create([
            'student_id' => $student->id,
            'current_term_id' => $currentTerm->id,
            'promoted_to_term_id' => $promotedToTerm->id,
            'meets_academic_criteria' => true,
            'meets_attendance_criteria' => true,
        ]);

        $promotion->approve();

        $this->assertEquals($promotedToTerm->id, $student->fresh()->current_term_id);
    }

    public function test_cannot_approve_promotion_to_unrelated_or_earlier_term(): void
    {
        $promotion = $this->eligiblePendingPromotion();
        $unrelatedTerm = Term::factory()->create(['term_number' => 2]);
        $promotion->update(['promoted_to_term_id' => $unrelatedTerm->id]);

        $this->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertSessionHas('error', 'Promotion target term must belong to the same program/batch and be later than the current term.');

        $this->assertEquals('pending', $promotion->fresh()->status);
        $this->assertEquals($promotion->current_term_id, $promotion->student->fresh()->current_term_id);
    }

    public function test_can_reject_with_remarks()
    {
        $promotion = TermPromotion::factory()->create(['status' => 'pending']);
        $remarks = 'Does not meet minimum CGPA requirement';

        $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => $remarks,
        ]);

        $this->assertEquals($remarks, $promotion->fresh()->remarks);
    }

    public function test_reviewed_promotion_history_cannot_be_reapproved_rejected_or_edited()
    {
        $promotion = $this->eligiblePendingPromotion();
        $promotion->approve();

        $this->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertSessionHas('error');

        $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => 'Changing historical decision',
        ])->assertSessionHas('error');

        $this->put("/academic/term-promotions/{$promotion->id}", [
            'status' => 'pending',
            'remarks' => 'Reopening history',
        ])->assertRedirect(route('academic.term-promotions.show', $promotion))
            ->assertSessionHas('error');

        $this->assertEquals('approved', $promotion->fresh()->status);
    }

    public function test_cannot_approve_stale_promotion_when_student_left_source_term()
    {
        $promotion = $this->eligiblePendingPromotion();
        $otherTerm = Term::factory()->create();
        $promotion->student->update(['current_term_id' => $otherTerm->id]);

        $this->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertSessionHas('error');

        $this->assertEquals('pending', $promotion->fresh()->status);
        $this->assertEquals($otherTerm->id, $promotion->student->fresh()->current_term_id);
    }

    public function test_cannot_approve_promotion_for_inactive_student(): void
    {
        $promotion = $this->eligiblePendingPromotion();
        $promotion->student->update(['status' => 'inactive']);

        $this->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertSessionHas('error', 'Inactive or archived students cannot be promoted.');

        $this->assertEquals('pending', $promotion->fresh()->status);
        $this->assertEquals($promotion->current_term_id, $promotion->student->fresh()->current_term_id);
    }

    public function test_bulk_approve_processes_only_reviewable_eligible_current_term_promotions()
    {
        $eligible = $this->eligiblePendingPromotion();
        $reviewed = $this->eligiblePendingPromotion();
        $reviewed->approve();
        $stale = $this->eligiblePendingPromotion();
        $stale->student->update(['current_term_id' => Term::factory()->create()->id]);

        $this->post(route('academic.term-promotions.bulk-approve'), [
            'promotion_ids' => [$eligible->id, $reviewed->id, $stale->id],
        ])->assertSessionHas('success');

        $this->assertEquals('approved', $eligible->fresh()->status);
        $this->assertEquals('approved', $reviewed->fresh()->status);
        $this->assertEquals('pending', $stale->fresh()->status);
        $this->assertEquals($eligible->promoted_to_term_id, $eligible->student->fresh()->current_term_id);
    }

    public function test_bulk_approve_skips_inactive_students(): void
    {
        $eligible = $this->eligiblePendingPromotion();
        $inactive = $this->eligiblePendingPromotion();
        $inactive->student->update(['status' => 'inactive']);

        $this->post(route('academic.term-promotions.bulk-approve'), [
            'promotion_ids' => [$eligible->id, $inactive->id],
        ])->assertSessionHas('success');

        $this->assertEquals('approved', $eligible->fresh()->status);
        $this->assertEquals('pending', $inactive->fresh()->status);
        $this->assertEquals($inactive->current_term_id, $inactive->student->fresh()->current_term_id);
    }

    public function test_generate_skips_inactive_students_in_source_term(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $currentTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term One',
        ]);
        Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 2,
            'name' => 'Term Two',
        ]);

        $activeStudent = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $currentTerm->id,
            'status' => 'active',
        ]);
        $inactiveStudent = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_term_id' => $currentTerm->id,
            'status' => 'inactive',
        ]);

        $this->post(route('academic.term-promotions.generate'), [
            'term_id' => $currentTerm->id,
            'cgpa_threshold' => 0,
            'attendance_threshold' => 0,
        ])->assertRedirect(route('academic.term-promotions.index'));

        $this->assertDatabaseHas('term_promotions', [
            'student_id' => $activeStudent->id,
            'current_term_id' => $currentTerm->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('term_promotions', [
            'student_id' => $inactiveStudent->id,
            'current_term_id' => $currentTerm->id,
        ]);
    }

    public function test_generate_uses_only_published_enrolled_attendance_for_promotion_criteria(): void
    {
        $program = Program::factory()->create();
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $semester = Semester::factory()->create(['number' => 1]);
        $currentTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term One',
        ]);
        Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 2,
            'name' => 'Term Two',
        ]);

        $student = Student::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'current_term_id' => $currentTerm->id,
            'status' => 'active',
        ]);
        $enrolledSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Promotion Enrolled Attendance']);
        $draftSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Promotion Draft Attendance']);
        $unenrolledSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Promotion Unenrolled Attendance']);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $enrolledSubject->id,
            'term_id' => $currentTerm->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $currentTerm->id,
            'semester_id' => $semester->id,
            'subject_id' => $enrolledSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
        ]);
        $draftEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $currentTerm->id,
            'semester_id' => $semester->id,
            'subject_id' => $draftSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'draft',
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $currentTerm->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => $this->userWithRole('admin')->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $currentTerm->id,
            'semester_id' => $semester->id,
            'subject_id' => $enrolledSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        $unenrolledEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $currentTerm->id,
            'semester_id' => $semester->id,
            'subject_id' => $unenrolledSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
        ]);

        Attendance::create(['student_id' => $student->id, 'timetable_entry_id' => $publishedEntry->id, 'date' => now()->subDays(4), 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'timetable_entry_id' => $publishedEntry->id, 'date' => now()->subDays(3), 'status' => 'absent']);
        foreach ([$draftEntry, $draftVersionEntry, $unenrolledEntry] as $entry) {
            Attendance::create(['student_id' => $student->id, 'timetable_entry_id' => $entry->id, 'date' => now()->subDays(2), 'status' => 'present']);
        }

        $this->post(route('academic.term-promotions.generate'), [
            'term_id' => $currentTerm->id,
            'cgpa_threshold' => 0,
            'attendance_threshold' => 75,
        ])->assertRedirect(route('academic.term-promotions.index'));

        $promotion = TermPromotion::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(50.0, (float) $promotion->attendance_percentage);
        $this->assertFalse((bool) $promotion->meets_attendance_criteria);
    }
}
