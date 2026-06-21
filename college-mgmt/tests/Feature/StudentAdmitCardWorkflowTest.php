<?php

namespace Tests\Feature;

use App\Models\{Exam, ExamRegistration, Program, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAdmitCardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $program = Program::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create(['user_id' => $user->id, 'program_id' => $program->id]);

        return [$user, $student, $program];
    }

    public function test_student_admit_cards_show_only_subject_enrolled_exams_and_block_direct_download(): void
    {
        [$user, $student, $program] = $this->makeStudent();
        $term = Term::factory()->create();
        $eligibleSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Eligible Accounting']);
        $otherSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Hidden Finance']);
        $eligibleExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $eligibleSubject->id,
            'term_id' => $term->id,
            'name' => 'Eligible Accounting Final',
            'exam_date' => now()->addWeek(),
        ]);
        $otherExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $otherSubject->id,
            'term_id' => $term->id,
            'name' => 'Hidden Finance Final',
            'exam_date' => now()->addWeek(),
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $eligibleSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        ExamRegistration::create([
            'student_id' => $student->id,
            'exam_id' => $eligibleExam->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->get(route('student.admit-cards.index'))
            ->assertStatus(200)
            ->assertSee('Eligible Accounting Final')
            ->assertSee(route('student.admit-cards.download', $eligibleExam), false)
            ->assertDontSee('Hidden Finance Final')
            ->assertDontSee(route('student.admit-cards.download', $otherExam), false);

        $this->actingAs($user)
            ->get(route('student.admit-cards.download', $otherExam))
            ->assertForbidden();
    }

    public function test_student_admit_card_empty_state_explains_release_and_eligibility_conditions(): void
    {
        [$user] = $this->makeStudent();

        $this->actingAs($user)
            ->get(route('student.admit-cards.index'))
            ->assertOk()
            ->assertSee('No admit cards available yet')
            ->assertSee('CoE schedules a future exam')
            ->assertSee('exam registration is approved')
            ->assertSee('attendance and fee clearance are verified')
            ->assertSee('exam is still unpublished')
            ->assertSee('contact the Exam Cell')
            ->assertDontSee('No Upcoming Exams')
            ->assertDontSee('Admit cards will appear here once exams are scheduled for your program.')
            ->assertDontSee('â');
    }

    public function test_student_admit_card_renders_eligible_exam_without_corrupted_fallback_text(): void
    {
        [$user, $student, $program] = $this->makeStudent();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Readable Fallback Subject']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Readable Fallback Final',
            'exam_date' => now()->addWeek(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100,
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        ExamRegistration::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->get(route('student.admit-cards.index'))
            ->assertOk()
            ->assertSee('Readable Fallback Final')
            ->assertSee('10:00 AM')
            ->assertSee('12:00 PM')
            ->assertSee('Venue to be announced')
            ->assertSee('100')
            ->assertDontSee('TBA')
            ->assertDontSee('Not set')
            ->assertDontSee('â');
    }

    public function test_student_admit_card_pdf_uses_readable_missing_data_labels(): void
    {
        $student = Student::factory()->make([
            'id' => 1001,
            'enrollment_number' => null,
            'roll_number' => null,
            'program_id' => null,
            'batch_id' => null,
        ]);
        $student->setRelation('user', null);
        $student->setRelation('program', null);
        $student->setRelation('batch', null);

        $exam = Exam::factory()->make([
            'id' => 2001,
            'name' => 'Readable Admit Card Exam',
            'exam_date' => null,
            'start_time' => null,
            'end_time' => null,
            'total_marks' => null,
            'passing_marks' => null,
        ]);
        $exam->setRelation('subject', null);
        $exam->setRelation('classroom', null);
        $exam->setRelation('semester', null);
        $exam->setRelation('term', null);

        $html = view('student.admit-card-pdf', compact('student', 'exam'))->render();

        $this->assertStringContainsString('Admit Card - Readable Admit Card Exam', $html);
        $this->assertStringContainsString('Student name missing', $html);
        $this->assertStringContainsString('Enrollment number pending', $html);
        $this->assertStringContainsString('Program not linked', $html);
        $this->assertStringContainsString('Roll number pending', $html);
        $this->assertStringContainsString('Batch not linked', $html);
        $this->assertStringContainsString('Academic year not linked', $html);
        $this->assertStringContainsString('Subject not linked', $html);
        $this->assertStringContainsString('Subject code not linked', $html);
        $this->assertStringContainsString('Exam date not announced', $html);
        $this->assertStringContainsString('Time not announced', $html);
        $this->assertStringContainsString('Venue to be announced', $html);
        $this->assertStringContainsString('Max marks pending / Passing marks pending', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('&mdash;', $html);
        $this->assertStringNotContainsString('â', $html);
    }

    public function test_student_admit_card_requires_approved_registration_fee_and_attendance_clearance(): void
    {
        [$user, $student, $program] = $this->makeStudent();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Operations Research']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Operations Research Final',
            'exam_date' => now()->addWeek(),
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        foreach ([
            ['status' => 'pending', 'attendance_eligible' => true, 'fee_cleared' => true],
            ['status' => 'approved', 'attendance_eligible' => false, 'fee_cleared' => true],
            ['status' => 'approved', 'attendance_eligible' => true, 'fee_cleared' => false],
        ] as $state) {
            ExamRegistration::query()->delete();
            ExamRegistration::create([
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                ...$state,
            ]);

            $this->actingAs($user)
                ->get(route('student.admit-cards.index'))
                ->assertStatus(200)
                ->assertDontSee('Operations Research Final');

            $this->actingAs($user)
                ->get(route('student.admit-cards.download', $exam))
                ->assertForbidden();
        }
    }

    public function test_inactive_student_cannot_view_or_download_admit_card_from_stale_approval(): void
    {
        [$user, $student, $program] = $this->makeStudent();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Archived Student Subject']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Archived Student Final',
            'exam_date' => now()->addWeek(),
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        ExamRegistration::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => User::factory()->create()->id,
        ]);
        $student->update(['status' => 'inactive']);

        $this->actingAs($user)
            ->get(route('student.admit-cards.index'))
            ->assertStatus(200)
            ->assertDontSee('Archived Student Final')
            ->assertDontSee(route('student.admit-cards.download', $exam), false);

        $this->actingAs($user)
            ->get(route('student.admit-cards.download', $exam))
            ->assertForbidden();
    }

    public function test_result_published_exam_does_not_issue_admit_card_from_stale_approval(): void
    {
        [$user, $student, $program] = $this->makeStudent();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Published Result Subject']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Published Result Future Exam',
            'exam_date' => now()->addWeek(),
            'published_at' => now(),
            'published_by' => User::factory()->create()->id,
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        ExamRegistration::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->get(route('student.admit-cards.index'))
            ->assertStatus(200)
            ->assertDontSee('Published Result Future Exam')
            ->assertDontSee(route('student.admit-cards.download', $exam), false);

        $this->actingAs($user)
            ->get(route('student.admit-cards.download', $exam))
            ->assertForbidden();
    }
}
