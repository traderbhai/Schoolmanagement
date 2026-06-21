<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
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

class DeanDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function deanUser(): User
    {
        Role::firstOrCreate(['name' => 'dean_academics', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('dean_academics');

        return $user;
    }

    private function pendingDeanApproval(?Program $program = null, array $extra = []): ApprovalWorkflow
    {
        $applicant = Applicant::factory()->create(['program_id' => $program?->id ?? Program::factory()->create()->id]);

        return ApprovalWorkflow::create(array_merge([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'dean_academics',
            'status' => 'pending',
        ], $extra));
    }

    public function test_dean_dashboard_prioritizes_overdue_approvals(): void
    {
        $user = $this->deanUser();
        $program = Program::factory()->create(['name' => 'BCA']);
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'passing_marks' => 40,
            'published_at' => now(),
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 80,
        ]);

        $this->pendingDeanApproval($program, ['due_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertStatus(200)
            ->assertSee('Dean Priority')
            ->assertSee('Clear 1 overdue dean approval')
            ->assertSee('BCA')
            ->assertSee('100%')
            ->assertSee(route('dean.approvals'), false);
    }

    public function test_dean_academic_dashboards_exclude_unpublished_draft_marks(): void
    {
        $user = $this->deanUser();
        $program = Program::factory()->create(['name' => 'MBA']);
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'passing_marks' => 40,
            'published_at' => now(),
            'name' => 'Published Term Exam',
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'passing_marks' => 40,
            'published_at' => null,
            'name' => 'Draft Internal Marks',
        ]);
        $publishedStudent = Student::factory()->create(['program_id' => $program->id]);
        $draftStudent = Student::factory()->create(['program_id' => $program->id]);

        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $publishedStudent->id,
            'marks_obtained' => 82,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $draftStudent->id,
            'marks_obtained' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertStatus(200)
            ->assertSee('Published Term Exam')
            ->assertDontSee('Draft Internal Marks')
            ->assertDontSee($draftStudent->user->name);

        $this->actingAs($user)
            ->get(route('dean.academics'))
            ->assertStatus(200)
            ->assertSee($publishedStudent->user->name)
            ->assertDontSee($draftStudent->user->name)
            ->assertDontSee('10.0%');
    }

    public function test_dean_academics_empty_top_performer_state_explains_published_result_requirement(): void
    {
        $user = $this->deanUser();
        Program::factory()->create(['name' => 'No Result Program', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('dean.academics'))
            ->assertStatus(200)
            ->assertSee('No published result data is available yet')
            ->assertSee('Publish official exam results before using the top-performer view')
            ->assertDontSee('No data.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_dean_attendance_views_ignore_draft_timetable_history(): void
    {
        $user = $this->deanUser();
        $program = Program::factory()->create(['name' => 'BCom Official Attendance', 'is_active' => true]);
        $course = Course::factory()->create();
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Dean Attendance Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'present',
                'marked_by' => $teacher->user_id,
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(4)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $dashboard = $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertOk();
        $this->assertSame(100.0, $dashboard->viewData('attendancePct'));
        $this->assertTrue($dashboard->viewData('atRiskStudents')->isEmpty());
        $this->assertSame('No urgent dean action today', $dashboard->viewData('deanPriority')['title']);

        $attendance = $this->actingAs($user)
            ->get(route('dean.attendance'))
            ->assertOk();
        $programStats = $attendance->viewData('programs')->firstWhere('name', 'BCom Official Attendance');
        $this->assertSame(100.0, $programStats->att_pct);
        $this->assertSame(2, $programStats->att_total);
    }

    public function test_dean_approval_action_generates_offer_and_only_one_program_chair_approval(): void
    {
        $user = $this->deanUser();
        $approval = $this->pendingDeanApproval();

        $this->actingAs($user)
            ->post(route('dean.approve', $approval), ['remarks' => 'Cleared'])
            ->assertRedirect();

        $this->assertDatabaseHas('offer_letters', [
            'applicant_id' => $approval->approvable_id,
            'status' => 'issued',
        ]);
        $this->assertSame(1, ApprovalWorkflow::where('approvable_type', Applicant::class)
            ->where('approvable_id', $approval->approvable_id)
            ->where('approver_role', 'program_chair')
            ->where('status', 'pending')
            ->count());

        $this->actingAs($user)
            ->post(route('dean.approve', $approval), ['remarks' => 'Duplicate'])
            ->assertForbidden();
    }

    public function test_dean_cannot_approve_non_dean_approval(): void
    {
        $user = $this->deanUser();
        $approval = ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => Applicant::factory()->create()->id,
            'approver_role' => 'hod',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('dean.approve', $approval), ['remarks' => 'Wrong queue'])
            ->assertForbidden();
    }
}
