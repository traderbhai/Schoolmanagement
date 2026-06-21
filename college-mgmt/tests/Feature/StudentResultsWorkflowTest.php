<?php

namespace Tests\Feature;

use App\Models\{AcademicTranscript, Exam, ExamResult, Program, Semester, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentResultsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function studentUser(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        return [$user, $student, $program];
    }

    public function test_student_results_use_canonical_subject_enrollments_without_legacy_enrollment_rows(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

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
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'credits' => 4,
            'name' => 'Canonical Result Subject',
            'code' => 'CRS201',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'name' => 'Canonical Result Exam',
            'total_marks' => 100,
            'passing_marks' => 35,
            'published_at' => now(),
        ]);
        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 82,
            'is_absent' => false,
        ]);

        $this->actingAs($user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Term 1')
            ->assertSee('Canonical Result Subject')
            ->assertSee('82')
            ->assertSee('Pass')
            ->assertSee(route('student.reports.grade-card', $semester), false)
            ->assertDontSee('Grade card pending publication');

        $gradeCard = $this->actingAs($user)
            ->get(route('student.reports.grade-card', $semester));

        $gradeCard->assertOk();
        $this->assertSame('application/pdf', $gradeCard->headers->get('content-type'));

        $report = app(\App\Services\GradeService::class)->calculateStudentSemesterReport($student->id, $semester->id);
        $this->assertSame(4, $report['total_credits']);
        $this->assertSame('Pass', $report['result']);
        $this->assertSame(9.0, app(\App\Services\GradeService::class)->calculateCGPA($student->id));
    }

    public function test_student_results_hide_unpublished_draft_marks_from_reports_and_cgpa(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

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
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'credits' => 4,
            'name' => 'Draft Hidden Subject',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'name' => 'Draft Result Exam',
            'total_marks' => 100,
            'published_at' => null,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 95,
            'is_absent' => false,
        ]);

        $this->actingAs($user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Draft Hidden Subject')
            ->assertDontSee('95.00/100', false)
            ->assertSee('Grade card pending publication')
            ->assertDontSee(route('student.reports.grade-card', $semester), false);

        $this->actingAs($user)
            ->get(route('student.reports.grade-card', $semester))
            ->assertNotFound();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.reports.grade-card', [$student, $semester]))
            ->assertNotFound();

        $report = app(\App\Services\GradeService::class)->calculateStudentSemesterReport($student->id, $semester->id);
        $this->assertSame('pending', $report['subjects'][0]['status']);
        $this->assertSame(0.0, app(\App\Services\GradeService::class)->calculateCGPA($student->id));
    }

    public function test_student_official_transcript_download_requires_issued_snapshot(): void
    {
        [$user] = $this->studentUser();

        $this->actingAs($user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Official transcript not issued')
            ->assertDontSee(route('student.transcript.download'), false);

        $this->actingAs($user)
            ->get(route('student.transcript.download'))
            ->assertRedirect(route('student.results'))
            ->assertSessionHas('error', 'Your official transcript has not been issued yet. Contact the Exam Cell for transcript issuance.');
    }

    public function test_student_can_download_only_existing_issued_transcript_snapshot(): void
    {
        [$user, $student] = $this->studentUser();

        AcademicTranscript::create([
            'student_id' => $student->id,
            'academic_year' => '2026-27',
            'cgpa' => 8.80,
            'total_credits_earned' => 4,
            'status' => 'issued',
            'issued_at' => now()->subDay(),
            'semester_data' => [
                'semester_reports' => [[
                    'term' => ['name' => 'Term 1'],
                    'sgpa' => 8.8,
                    'earned_credits' => 4,
                    'total_credits' => 4,
                    'subjects' => [[
                        'subject' => ['name' => 'Issued Transcript Subject', 'code' => 'ITS101'],
                        'credits' => 4,
                        'obtained' => 88,
                        'total' => 100,
                        'pct' => 88,
                        'grade' => ['letter' => 'A+', 'points' => 9.0],
                        'status' => 'pass',
                    ]],
                ]],
                'cgpa' => 8.8,
                'total_credits' => 4,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee(route('student.transcript.download'), false)
            ->assertDontSee('Official transcript not issued');

        $response = $this->actingAs($user)
            ->get(route('student.transcript.download'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_grade_card_pdf_uses_readable_missing_data_labels(): void
    {
        $student = Student::factory()->make([
            'id' => 1001,
            'enrollment_number' => null,
            'course_id' => null,
            'program_id' => null,
        ]);
        $student->setRelation('user', null);
        $student->setRelation('course', null);
        $student->setRelation('program', null);

        $semester = Semester::factory()->make(['name' => null]);

        $results = [[
            'subject' => null,
            'credits' => 4,
            'obtained' => null,
            'max' => null,
            'pct' => null,
            'grade' => null,
            'status' => 'pending',
        ]];

        $html = view('pdf.grade-card', [
            'student' => $student,
            'semester' => $semester,
            'results' => $results,
            'sgpa' => 0,
            'cgpa' => 0,
        ])->render();

        $this->assertStringContainsString('Academic Record - Official Document', $html);
        $this->assertStringContainsString('Student name missing', $html);
        $this->assertStringContainsString('Enrollment number pending', $html);
        $this->assertStringContainsString('Program not linked', $html);
        $this->assertStringContainsString('Semester not linked', $html);
        $this->assertStringContainsString('Subject not linked', $html);
        $this->assertStringContainsString('Marks pending', $html);
        $this->assertStringContainsString('Max marks pending', $html);
        $this->assertStringContainsString('Result pending', $html);
        $this->assertStringContainsString('Grade pending', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('&bull;', $html);
        $this->assertStringNotContainsString('â', $html);
    }
}
