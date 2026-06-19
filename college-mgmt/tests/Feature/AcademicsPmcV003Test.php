<?php

namespace Tests\Feature;

use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcReviewMeeting;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcWorkItem;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\AcademicPmcOperatingService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsPmcV003Test extends TestCase
{
    use RefreshDatabase;

    private function seedPmcFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Aarav PMC v003']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'status' => 'active']);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_head_can_open_all_v003_surfaces(): void
    {
        $chair = $this->seedPmcFixture();

        foreach ([
            'academics.pmc.command' => 'PMC Command OS',
            'academics.pmc.workbench' => 'PMC Workbench',
            'academics.pmc.curriculum-governance' => 'PMC Curriculum Governance',
            'academics.pmc.faculty-workload' => 'PMC Faculty Workload Governance',
            'academics.pmc.timetable-control' => 'PMC Timetable Control Room',
            'academics.pmc.student-success' => 'PMC Student Success Command',
            'academics.pmc.reviews' => 'PMC Reviews And Actions',
            'academics.pmc.reports' => 'PMC Reports',
        ] as $route => $text) {
            $this->actingAs($chair)->get(route($route))->assertOk()->assertSee($text);
        }
    }

    public function test_pmc_work_item_review_saved_view_and_export_flows_work(): void
    {
        $chair = $this->seedPmcFixture();

        $this->actingAs($chair)->post(route('academics.pmc.work-items.store'), [
            'work_type' => 'curriculum',
            'title' => 'Test PMC Work Item',
            'priority' => 'high',
            'severity' => 'high',
            'status' => 'open',
            'due_at' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $item = AcademicPmcWorkItem::where('title', 'Test PMC Work Item')->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.work-items.update', $item), [
            'priority' => 'high',
            'severity' => 'high',
            'status' => 'done',
            'due_at' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_work_items', ['id' => $item->id, 'status' => 'done']);

        $this->actingAs($chair)->post(route('academics.pmc.reviews.store'), [
            'title' => 'Test PMC Review',
            'review_type' => 'weekly_pmc',
            'agenda' => 'Review test action',
        ])->assertRedirect();
        $this->assertTrue(AcademicPmcReviewMeeting::where('title', 'Test PMC Review')->exists());

        $this->actingAs($chair)->post(route('academics.pmc.saved-views.store'), [
            'name' => 'Test PMC View',
            'surface' => 'command',
            'filters' => ['severity' => 'critical'],
            'is_default' => true,
        ])->assertRedirect();
        $this->assertTrue(AcademicPmcSavedView::where('name', 'Test PMC View')->exists());

        $this->actingAs($chair)->get(route('academics.pmc.export', 'workbench'))->assertOk();
        $this->assertTrue(AcademicPmcExportLog::where('report_key', 'workbench')->exists());
    }

    public function test_pmc_student_monitoring_ignores_draft_timetable_attendance(): void
    {
        $chair = $this->seedPmcFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $department = Department::where('code', 'MGT')->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $semester = Semester::where('is_current', true)->firstOrFail();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $teacher = Teacher::factory()->create(['department_id' => $department->id]);

        $officialStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Official PMC Monitoring Risk'])->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $draftOnlyStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft PMC Monitoring Risk'])->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $officialStudent->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $draftOnlyStudent->id,
                'timetable_entry_id' => $draftEntry->id,
                'date' => now()->subDays($day + 3)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $data = app(AcademicPmcOperatingService::class)->studentMonitoring($chair);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Official PMC Monitoring Risk'));
        $this->assertFalse($titles->contains('Draft PMC Monitoring Risk'));
    }

    public function test_pmc_student_monitoring_uses_only_published_results_for_weak_performance(): void
    {
        $chair = $this->seedPmcFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $department = Department::where('code', 'MGT')->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();

        $publishedStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Published PMC Weak Result'])->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $draftStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft PMC Weak Result'])->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => now(),
            'passing_marks' => 40,
            'total_marks' => 100,
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => null,
            'passing_marks' => 40,
            'total_marks' => 100,
        ]);

        ExamResult::factory()->create(['exam_id' => $publishedExam->id, 'student_id' => $publishedStudent->id, 'marks_obtained' => 22]);
        ExamResult::factory()->create(['exam_id' => $draftExam->id, 'student_id' => $draftStudent->id, 'marks_obtained' => 12]);

        $data = app(AcademicPmcOperatingService::class)->studentMonitoring($chair);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Published PMC Weak Result'));
        $this->assertFalse($titles->contains('Draft PMC Weak Result'));
    }

    public function test_pmc_timetable_readiness_treats_draft_version_entries_as_unpublished(): void
    {
        $chair = $this->seedPmcFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $department = Department::where('code', 'MGT')->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $semester = Semester::where('is_current', true)->firstOrFail();
        $term = \App\Models\Term::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $teacher = Teacher::factory()->create(['department_id' => $department->id]);
        $slotOne = TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '10:00:00', 'sort_order' => 2]);
        $service = app(AcademicPmcOperatingService::class);
        $before = $service->timetableReadiness($chair);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => $slotOne->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $chair->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => $slotTwo->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        $after = $service->timetableReadiness($chair);

        $this->assertSame($before['metrics']['published_slots'] + 1, $after['metrics']['published_slots']);
        $this->assertSame($before['metrics']['draft_slots'] + 1, $after['metrics']['draft_slots']);
    }

    public function test_non_academic_user_cannot_access_pmc_v003(): void
    {
        $this->seedPmcFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student)->get(route('academics.pmc.command'))->assertForbidden();
    }
}
