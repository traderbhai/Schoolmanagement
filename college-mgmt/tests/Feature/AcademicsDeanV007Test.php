<?php

namespace Tests\Feature;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanExportLog;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\AcademicDeanAttentionService;
use App\Services\AcademicDeanRiskService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsDeanV007Test extends TestCase
{
    use RefreshDatabase;

    private function seedDeanFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Aarav Dean Risk']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'semester', 'student');
    }

    public function test_dean_can_open_command_os_with_branch_health_and_risk(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertSee('Dean Academics Command OS')
            ->assertSee('Branch Health')
            ->assertSee('PMC')
            ->assertSee('CoE / Examination')
            ->assertSee('IQAC')
            ->assertSee('Program Leadership')
            ->assertSee('Course Delivery')
            ->assertSee('Program Risk Heatmap')
            ->assertSee('Review Actions');
    }

    public function test_non_dean_branch_users_cannot_access_dean_os(): void
    {
        $this->seedDeanFixture();
        $pmc = User::where('email', 'pmc.manager@college.com')->firstOrFail();

        $this->actingAs($pmc)
            ->get(route('academics.dean-os.index'))
            ->assertForbidden();
    }

    public function test_dean_attention_and_program_risk_are_database_backed(): void
    {
        $fixture = $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $entry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);
        Attendance::create(['student_id' => $fixture['student']->id, 'timetable_entry_id' => $entry->id, 'date' => now()->subDays(2), 'status' => 'absent']);
        Attendance::create(['student_id' => $fixture['student']->id, 'timetable_entry_id' => $entry->id, 'date' => now()->subDay(), 'status' => 'late']);

        $queue = app(AcademicDeanAttentionService::class)->queue('action_items_overdue');
        $this->assertGreaterThan(0, $queue['count']);

        $risks = app(AcademicDeanRiskService::class)->programRisks();
        $this->assertTrue($risks->contains(fn ($risk) => $risk['program']->code === 'PGDM'));

        $this->actingAs($dean)
            ->get(route('academics.dean-os.program-risk'))
            ->assertOk()
            ->assertSee('Program Risk Heatmap')
            ->assertSee('PGDM');

        $this->actingAs($dean)
            ->get(route('academics.dean-os.attention', 'attendance_risk'))
            ->assertOk()
            ->assertSee('Aarav Dean Risk')
            ->assertDontSee('Student #'.$fixture['student']->id);
    }

    public function test_dean_weak_performance_signals_use_only_published_results(): void
    {
        $fixture = $this->seedDeanFixture();

        $publishedStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Published Dean Weak Result'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);
        $draftStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft Dean Weak Result'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);

        $publishedExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'published_at' => now(),
            'passing_marks' => 40,
            'total_marks' => 100,
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'published_at' => null,
            'passing_marks' => 40,
            'total_marks' => 100,
        ]);

        ExamResult::factory()->create(['exam_id' => $publishedExam->id, 'student_id' => $publishedStudent->id, 'marks_obtained' => 20]);
        ExamResult::factory()->create(['exam_id' => $draftExam->id, 'student_id' => $draftStudent->id, 'marks_obtained' => 10]);

        $weakQueue = app(AcademicDeanAttentionService::class)->queue('weak_academic_performance');
        $titles = collect($weakQueue['items'])->pluck('title');

        $this->assertTrue($titles->contains('Published Dean Weak Result'));
        $this->assertFalse($titles->contains('Draft Dean Weak Result'));

        $risk = app(AcademicDeanRiskService::class)
            ->programRisks()
            ->first(fn ($row) => $row['program']->id === $fixture['program']->id);

        $this->assertSame(1, $risk['metrics']['failedResults']);
    }

    public function test_dean_can_create_review_action_update_action_and_export(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $owner = User::where('email', 'pmc.manager@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->post(route('academics.dean-os.reviews.store'), [
                'title' => 'Dean Test Review',
                'review_type' => 'weekly_academic',
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
                'scope_type' => 'department',
                'summary' => 'Test review',
            ])
            ->assertRedirect();

        $meeting = AcademicDeanReviewMeeting::where('title', 'Dean Test Review')->firstOrFail();

        $this->actingAs($dean)
            ->post(route('academics.dean-os.actions.store'), [
                'meeting_id' => $meeting->id,
                'title' => 'Dean Test Action',
                'description' => 'Close test action',
                'source_type' => 'manual',
                'owner_user_id' => $owner->id,
                'priority' => 'high',
                'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $action = AcademicDeanActionItem::where('title', 'Dean Test Action')->firstOrFail();

        $this->actingAs($dean)
            ->patch(route('academics.dean-os.actions.update', $action), [
                'owner_user_id' => $owner->id,
                'priority' => 'high',
                'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'status' => 'done',
                'closure_note' => 'Closed in test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_dean_action_items', ['id' => $action->id, 'status' => 'done', 'closure_note' => 'Closed in test']);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.export', 'branch_health'))
            ->assertOk();

        $this->assertTrue(AcademicDeanExportLog::where('report_key', 'branch_health')->exists());
    }

    public function test_dean_handoff_reports_calendar_and_legacy_dashboard_links_render(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $handoff = DB::table('admission_handoff_records')
            ->leftJoin('applicants', 'applicants.id', '=', 'admission_handoff_records.applicant_id')
            ->leftJoin('users', 'users.id', '=', 'applicants.user_id')
            ->whereIn('admission_handoff_records.status', ['blocked', 'ready_for_academics', 'returned_for_correction'])
            ->select('admission_handoff_records.applicant_id', 'users.name as applicant_name', 'applicants.application_number')
            ->first();

        $this->actingAs($dean)->get(route('academics.dean-os.handoff'))->assertOk()->assertSee('Admission To Academics Handoff');
        $calendarResponse = $this->actingAs($dean)->get(route('academics.dean-os.calendar'))->assertOk()->assertSee('Dean Academic Calendar');
        if ($handoff) {
            $calendarResponse
                ->assertSee($handoff->applicant_name ?: $handoff->application_number)
                ->assertDontSee('Applicant #'.$handoff->applicant_id);
        }
        $this->actingAs($dean)->get(route('academics.dean-os.reports'))->assertOk()->assertSee('Dean Reports')->assertSee('Dean branch health');
        $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk()->assertSee('Dean OS');
    }
}
