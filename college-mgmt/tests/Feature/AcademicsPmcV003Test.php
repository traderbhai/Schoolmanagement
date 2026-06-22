<?php

namespace Tests\Feature;

use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcReviewMeeting;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcWorkItem;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Department;
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

    public function test_pmc_timetable_readiness_prefers_canonical_pmc_sessions_for_migrated_scope(): void
    {
        $chair = $this->seedPmcFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $department = Department::where('code', 'MGT')->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $semester = Semester::where('is_current', true)->firstOrFail();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id]);
        $teacher = Teacher::factory()->create(['department_id' => $department->id]);
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1]);
        $service = app(AcademicPmcOperatingService::class);
        $before = $service->timetableReadiness($chair);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'PMC Readiness Canonical Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
            'published_by' => $chair->id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $chair->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'PMC Readiness Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $chair->id,
            'status' => 'published',
            'scheduled_count' => 2,
            'quality_score' => 100,
        ]);

        foreach ([1 => 'locked', 2 => 'published'] as $index => $status) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'timetable_version_id' => $publishedVersion->id,
                'course_group_id' => $group->id,
                'session_index' => $index,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $slot->id,
                'status' => $status,
                'official_status' => 'published',
                'source_type' => 'generated',
                'published_at' => now(),
                'published_by' => $chair->id,
            ]);
        }
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => $group->id,
            'session_index' => 3,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);

        foreach (range(1, 4) as $day) {
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'timetable_slot_id' => $slot->id,
                'day_of_week' => $day,
                'is_active' => true,
                'status' => 'published',
            ]);
        }
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 5,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $data = $service->timetableReadiness($chair);
        $conflictItem = collect($data['items'])->firstWhere('source', 'canonical_pmc_official_sessions');
        $draftItem = collect($data['items'])->first(fn (array $item) => ($item['source'] ?? null) === 'canonical_pmc_generation_items' && $item['status'] === 'Published');

        $this->assertSame($before['metrics']['published_slots'] + 2, $data['metrics']['published_slots']);
        $this->assertSame($before['metrics']['draft_slots'] + 1, $data['metrics']['draft_slots']);
        $this->assertSame($before['metrics']['teacher_conflicts'] + 1, $data['metrics']['teacher_conflicts']);
        $this->assertNotNull($conflictItem);
        $this->assertNotNull($draftItem);
        $this->assertSame('Conflict', $conflictItem['status']);
        $this->assertSame('Published', $draftItem['status']);
    }

    public function test_program_chair_faculty_workload_page_prefers_canonical_pmc_sessions_for_migrated_scope(): void
    {
        $chair = $this->seedPmcFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $department = Department::where('code', 'MGT')->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $semester = Semester::where('is_current', true)->firstOrFail();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Canonical Workload Batch']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'name' => 'Canonical Workload Term']);
        $slot = TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1]);
        $room = Classroom::factory()->create();
        $canonicalTeacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Canonical Chair Workload Faculty'])->id,
            'department_id' => $department->id,
        ]);
        $legacyTeacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Stale Legacy Chair Workload Faculty'])->id,
            'department_id' => $department->id,
        ]);
        $draftTeacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft Chair Workload Faculty'])->id,
            'department_id' => $department->id,
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
            'published_by' => $chair->id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $chair->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Chair Workload Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $chair->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $publishedVersion->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 1,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'teacher_id' => $canonicalTeacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $chair->id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $draftTeacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);
        foreach (range(1, 3) as $day) {
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => $legacyTeacher->id,
                'timetable_slot_id' => $slot->id,
                'day_of_week' => $day,
                'is_active' => true,
                'status' => 'published',
            ]);
        }

        $this->actingAs($chair)
            ->get(route('chair.faculty.workload', ['term_id' => $term->id]))
            ->assertOk()
            ->assertSee('Canonical Chair Workload Faculty')
            ->assertSee('2.0')
            ->assertDontSee('Stale Legacy Chair Workload Faculty')
            ->assertDontSee('Draft Chair Workload Faculty');
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
