<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentFeatureSetting;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPublishedResultsReportingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function enableAcademicReports(): void
    {
        $academic = Department::firstOrCreate(
            ['code' => 'ACAD'],
            ['name' => 'Academics', 'head_name' => 'Dean Academics', 'is_active' => true]
        );

        DepartmentFeatureSetting::updateOrCreate(
            [
                'department_id' => $academic->id,
                'feature_key' => 'academic.reports',
            ],
            [
                'feature_name' => 'Academic Reports',
                'is_enabled' => true,
            ]
        );
    }

    private function publishedAndDraftResultsFixture(): array
    {
        $program = Program::factory()->create(['name' => 'BBA Analytics', 'is_active' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now(),
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Business Statistics',
        ]);
        $publishedStudentUser = User::factory()->create(['name' => 'Published Performer']);
        $draftStudentUser = User::factory()->create(['name' => 'Draft Only Student']);
        $publishedStudent = Student::factory()->create([
            'user_id' => $publishedStudentUser->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $draftStudent = Student::factory()->create([
            'user_id' => $draftStudentUser->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'total_marks' => 100,
            'passing_marks' => 40,
            'published_at' => now(),
            'exam_date' => now()->subDay(),
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'total_marks' => 100,
            'passing_marks' => 40,
            'published_at' => null,
            'exam_date' => now()->subDay(),
        ]);

        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $publishedStudent->id,
            'marks_obtained' => 80,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $draftStudent->id,
            'marks_obtained' => 20,
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $publishedStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'core',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $draftStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'core',
            'status' => 'active',
        ]);

        return compact('program', 'term', 'semester', 'subject', 'publishedStudent', 'draftStudent');
    }

    private function seedPublishedAndDraftAttendance(array $fixture, User $actor): void
    {
        $course = Course::factory()->create();
        $teacher = Teacher::factory()->create();
        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);

        foreach ([$fixture['publishedStudent'], $fixture['draftStudent']] as $student) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDay()->toDateString(),
                'status' => 'present',
                'marked_by' => $teacher->user_id,
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $fixture['draftStudent']->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(2)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $fixture['draftStudent']->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);
    }

    public function test_admin_official_reports_exclude_unpublished_draft_results(): void
    {
        $admin = $this->adminUser();
        $this->publishedAndDraftResultsFixture();

        $analyticsResponse = $this->actingAs($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Overall Pass Rate');
        $this->assertSame(100.0, $analyticsResponse->viewData('overallPassRate'));
        $this->assertSame(100.0, $analyticsResponse->viewData('programs')->firstWhere('name', 'BBA Analytics')->pass_rate);
        $this->assertSame(0, $analyticsResponse->viewData('gradeDistribution')['F']);

        $kpiResponse = $this->actingAs($admin)
            ->get(route('admin.institutional-kpi'))
            ->assertOk()
            ->assertSee('Pass Rate %');
        $this->assertSame(100.0, $kpiResponse->viewData('passRate'));
        $this->assertSame(100.0, $kpiResponse->viewData('programs')->firstWhere('name', 'BBA Analytics')->pass_rate);

        $aicteResponse = $this->actingAs($admin)
            ->get(route('admin.aicte-report'))
            ->assertOk()
            ->assertSee('BBA Analytics');
        $this->assertSame(100.0, $aicteResponse->viewData('programs')->firstWhere('name', 'BBA Analytics')->pass_rate);
    }

    public function test_admin_analytics_attendance_ignores_draft_timetable_history(): void
    {
        $admin = $this->adminUser();
        $fixture = $this->publishedAndDraftResultsFixture();
        $course = Course::factory()->create();
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create();

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);

        foreach ([$fixture['publishedStudent'], $fixture['draftStudent']] as $student) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDay()->toDateString(),
                'status' => 'present',
                'marked_by' => $teacher->user_id,
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $fixture['draftStudent']->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(2)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $fixture['draftStudent']->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Overall Attendance');

        $this->assertSame(100.0, $response->viewData('overallAttPct'));
        $this->assertSame(100.0, $response->viewData('programs')->firstWhere('name', 'BBA Analytics')->att_pct);
        $this->assertTrue($response->viewData('atRisk')->isEmpty());
    }

    public function test_pmc_reports_attendance_uses_only_published_timetable_history(): void
    {
        $admin = $this->adminUser();
        $this->enableAcademicReports();
        $fixture = $this->publishedAndDraftResultsFixture();
        $this->seedPublishedAndDraftAttendance($fixture, $admin);

        $defaultersResponse = $this->actingAs($admin)
            ->get(route('chair.reports.attendance-defaulters', ['threshold' => 75]))
            ->assertOk()
            ->assertSee('Attendance Defaulters');
        $this->assertTrue($defaultersResponse->viewData('defaulters')->isEmpty());

        $termResponse = $this->actingAs($admin)
            ->get(route('chair.reports.term-summary', ['term_id' => $fixture['term']->id]))
            ->assertOk()
            ->assertSee('BBA Analytics');

        $termStats = $termResponse->viewData('stats')
            ->first(fn($programStats) => $programStats->program->id === $fixture['program']->id);
        $this->assertSame(100.0, $termStats->att_pct);
    }

    public function test_program_chair_without_program_assignment_does_not_fallback_to_all_pmc_reports(): void
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $chair = User::factory()->create();
        $chair->assignRole('program_chair');
        $this->enableAcademicReports();
        $fixture = $this->publishedAndDraftResultsFixture();

        RoleProgramAssignment::where('user_id', $chair->id)->delete();

        $response = $this->actingAs($chair)
            ->get(route('chair.reports.term-summary', ['term_id' => $fixture['term']->id]))
            ->assertOk();

        $this->assertTrue($response->viewData('stats')->isEmpty());
        $response->assertDontSee('BBA Analytics');
    }

    public function test_pmc_reports_exclude_unpublished_draft_results(): void
    {
        $admin = $this->adminUser();
        $this->enableAcademicReports();
        $fixture = $this->publishedAndDraftResultsFixture();

        $subjectResponse = $this->actingAs($admin)
            ->get(route('chair.reports.subject-performance', ['term_id' => $fixture['term']->id]))
            ->assertOk()
            ->assertSee('Business Statistics');
        $subjectStats = $subjectResponse->viewData('subjects')->firstWhere('name', 'Business Statistics')->stats;
        $this->assertSame(1, $subjectStats->count);
        $this->assertSame(80.0, $subjectStats->avg_pct);
        $this->assertSame(100.0, $subjectStats->pass_rate);

        $termResponse = $this->actingAs($admin)
            ->get(route('chair.reports.term-summary', ['term_id' => $fixture['term']->id]))
            ->assertOk()
            ->assertSee('BBA Analytics')
            ->assertSee('80.0')
            ->assertSee('100.0%')
            ->assertSee('Published Performer')
            ->assertDontSee('Draft Only Student');
        $termStats = $termResponse->viewData('stats')
            ->first(fn($programStats) => $programStats->program->name === 'BBA Analytics');
        $this->assertSame(80.0, $termStats->avg_marks);
        $this->assertSame(100.0, $termStats->pass_rate);
        $this->assertTrue($termStats->top_students->contains(fn(Student $student) => $student->user_id === $fixture['publishedStudent']->user_id));
        $this->assertFalse($termStats->top_students->contains(fn(Student $student) => $student->user_id === $fixture['draftStudent']->user_id));
    }

    public function test_admin_consolidated_report_uses_published_result_service_contract(): void
    {
        $admin = $this->adminUser();
        $fixture = $this->publishedAndDraftResultsFixture();

        $publishedResponse = $this->actingAs($admin)
            ->get(route('admin.students.report', $fixture['publishedStudent']))
            ->assertOk();
        $this->assertSame('application/pdf', $publishedResponse->headers->get('content-type'));

        $this->actingAs($admin)
            ->get(route('admin.students.report', $fixture['draftStudent']))
            ->assertNotFound();
    }

    public function test_admin_consolidated_report_template_uses_readable_missing_data_labels(): void
    {
        $student = Student::factory()->make([
            'id' => 1001,
            'enrollment_number' => null,
            'course_id' => null,
            'program_id' => null,
            'department_id' => null,
        ]);
        $student->setRelation('user', null);
        $student->setRelation('course', null);
        $student->setRelation('program', null);
        $student->setRelation('department', null);

        $semesterReports = [[
            'semester' => null,
            'report' => [
                'sgpa' => 0,
                'result' => null,
                'subjects' => [[
                    'subject' => null,
                    'credits' => 4,
                    'obtained' => null,
                    'max' => null,
                    'pct' => null,
                    'grade' => null,
                    'status' => null,
                ]],
            ],
        ]];

        $html = view('admin.reports.consolidated', [
            'student' => $student,
            'semesterReports' => $semesterReports,
            'cgpa' => 0,
        ])->render();

        $this->assertStringContainsString('Student name missing', $html);
        $this->assertStringContainsString('Enrollment number pending', $html);
        $this->assertStringContainsString('Program not linked', $html);
        $this->assertStringContainsString('Department not linked', $html);
        $this->assertStringContainsString('Semester not linked - SGPA', $html);
        $this->assertStringContainsString('Result pending', $html);
        $this->assertStringContainsString('Subject not linked', $html);
        $this->assertStringContainsString('Marks pending', $html);
        $this->assertStringContainsString('Grade pending', $html);
        $this->assertStringContainsString('Points pending', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('â', $html);
    }
}
