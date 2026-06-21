<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\FeeDemand;
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

class StudentExamRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Exam Registration Student']);
        $user->assignRole('student');

        $program = Program::factory()->create();
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Managerial Economics',
        ]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'name' => 'End Term Economics',
            'exam_date' => now()->addWeek(),
        ]);

        return compact('user', 'student', 'program', 'subject', 'exam', 'term', 'semester');
    }

    private function addAttendance(Student $student, Subject $subject, Semester $semester, int $present, int $absent): void
    {
        $entry = TimetableEntry::factory()->create([
            'program_id' => $student->program_id,
            'course_id' => $student->course_id,
            'batch_id' => $student->batch_id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::factory()->create()->id,
        ]);

        for ($i = 0; $i < $present; $i++) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->subDays($i + 1)->toDateString(),
                'status' => 'present',
            ]);
        }

        for ($i = 0; $i < $absent; $i++) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->subDays($present + $i + 1)->toDateString(),
                'status' => 'absent',
            ]);
        }
    }

    public function test_exam_registration_page_shows_real_exam_name_and_clear_fee_state(): void
    {
        $fixture = $this->fixture();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.exam-reg.index'))
            ->assertStatus(200)
            ->assertSee('End Term Economics')
            ->assertSee('Managerial Economics')
            ->assertSee('Attendance not recorded yet; Exam Cell will verify eligibility.')
            ->assertSee('Clear')
            ->assertDontSee('No records yet')
            ->assertDontSee('Dues Pending');
    }

    public function test_exam_registration_hides_and_blocks_unenrolled_same_program_subject_exam(): void
    {
        $fixture = $this->fixture();
        $otherSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'name' => 'Unenrolled Finance',
        ]);
        $otherExam = Exam::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $otherSubject->id,
            'name' => 'End Term Finance',
            'exam_date' => now()->addWeek(),
        ]);
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.exam-reg.index'))
            ->assertOk()
            ->assertSee('End Term Economics')
            ->assertDontSee('End Term Finance');

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $otherExam))
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_registrations', [
            'student_id' => $fixture['student']->id,
            'exam_id' => $otherExam->id,
        ]);
    }

    public function test_student_dashboard_only_shows_upcoming_exams_for_enrolled_subjects(): void
    {
        $fixture = $this->fixture();
        $otherSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'name' => 'Unenrolled Analytics',
        ]);
        Exam::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $otherSubject->id,
            'name' => 'End Term Analytics',
            'exam_date' => now()->addWeek(),
        ]);
        Exam::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'name' => 'Published Future Economics',
            'exam_date' => now()->addWeek(),
            'published_at' => now(),
            'published_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('End Term Economics')
            ->assertDontSee('End Term Analytics')
            ->assertDontSee('Published Future Economics');
    }

    public function test_student_can_register_when_fee_clear_and_attendance_unknown_or_eligible(): void
    {
        $fixture = $this->fixture();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $fixture['exam']))
            ->assertRedirect()
            ->assertSessionHas('success', 'Registered for End Term Economics. Exam Cell will verify eligibility.');

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $fixture['student']->id,
            'exam_id' => $fixture['exam']->id,
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'status' => 'pending',
        ]);
    }

    public function test_inactive_student_cannot_register_for_exam_through_direct_route(): void
    {
        $fixture = $this->fixture();
        $fixture['student']->update(['status' => 'inactive']);
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $fixture['exam']))
            ->assertRedirect()
            ->assertSessionHas('error', 'Exam registration is available only for active students. Contact the Exam Cell for archived records.');

        $this->assertDatabaseMissing('exam_registrations', [
            'student_id' => $fixture['student']->id,
            'exam_id' => $fixture['exam']->id,
        ]);
    }

    public function test_direct_registration_is_blocked_for_fee_dues_or_low_attendance(): void
    {
        $fixture = $this->fixture();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'pending',
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $fixture['exam']))
            ->assertRedirect()
            ->assertSessionHas('error', 'Clear pending fee dues before exam registration.');

        $this->assertSame(0, ExamRegistration::count());

        FeeDemand::query()->delete();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);
        $this->addAttendance($fixture['student'], $fixture['subject'], $fixture['exam']->semester, present: 2, absent: 2);

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $fixture['exam']))
            ->assertRedirect()
            ->assertSessionHas('error', 'Attendance is below the 75% eligibility threshold for this subject.');

        $this->assertSame(0, ExamRegistration::count());
    }

    public function test_exam_registration_attendance_eligibility_ignores_draft_timetable_history(): void
    {
        $fixture = $this->fixture();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        $this->addAttendance($fixture['student'], $fixture['subject'], $fixture['semester'], present: 3, absent: 0);

        $draftEntry = TimetableEntry::factory()->create([
            'program_id' => $fixture['program']->id,
            'course_id' => $fixture['student']->course_id,
            'batch_id' => $fixture['student']->batch_id,
            'term_id' => $fixture['term']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'day_of_week' => 2,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(10)->toDateString(),
            'status' => 'absent',
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'batch_id' => $fixture['student']->batch_id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'program_id' => $fixture['program']->id,
            'course_id' => $fixture['student']->course_id,
            'batch_id' => $fixture['student']->batch_id,
            'term_id' => $fixture['term']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'day_of_week' => 3,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(11)->toDateString(),
            'status' => 'absent',
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.exam-reg.index'))
            ->assertOk()
            ->assertSee('100%')
            ->assertDontSee('attendance is below the 75% eligibility threshold');

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $fixture['exam']))
            ->assertRedirect()
            ->assertSessionHas('success', 'Registered for End Term Economics. Exam Cell will verify eligibility.');

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $fixture['student']->id,
            'exam_id' => $fixture['exam']->id,
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'status' => 'pending',
        ]);
    }

    public function test_reviewed_exam_registration_cannot_be_reset_to_pending_by_student_post(): void
    {
        $fixture = $this->fixture();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        foreach (['approved', 'rejected'] as $status) {
            ExamRegistration::query()->delete();
            $registration = ExamRegistration::create([
                'student_id' => $fixture['student']->id,
                'exam_id' => $fixture['exam']->id,
                'status' => $status,
                'attendance_eligible' => $status === 'approved',
                'fee_cleared' => true,
                'remarks' => 'Reviewed by Exam Cell',
                'approved_by' => User::factory()->create()->id,
            ]);

            $this->actingAs($fixture['user'])
                ->post(route('student.exam-reg.register', $fixture['exam']))
                ->assertRedirect()
                ->assertSessionHas('error', 'This exam registration has already been reviewed and cannot be changed.');

            $registration->refresh();
            $this->assertSame($status, $registration->status);
            $this->assertSame('Reviewed by Exam Cell', $registration->remarks);
        }
    }

    public function test_student_cannot_register_for_result_published_exam(): void
    {
        $fixture = $this->fixture();
        $fixture['exam']->forceFill([
            'published_at' => now(),
            'published_by' => User::factory()->create()->id,
            'exam_date' => now()->addWeek(),
        ])->save();
        FeeDemand::factory()->create([
            'student_id' => $fixture['student']->id,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.exam-reg.index'))
            ->assertOk()
            ->assertDontSee('End Term Economics');

        $this->actingAs($fixture['user'])
            ->post(route('student.exam-reg.register', $fixture['exam']))
            ->assertRedirect()
            ->assertSessionHas('error', 'Registration is closed because this exam result has already been published.');

        $this->assertDatabaseMissing('exam_registrations', [
            'student_id' => $fixture['student']->id,
            'exam_id' => $fixture['exam']->id,
        ]);
    }
}
