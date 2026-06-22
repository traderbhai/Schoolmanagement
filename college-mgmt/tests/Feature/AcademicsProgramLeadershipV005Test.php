<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\AcademicScopeAssignment;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Services\AcademicProgramLeadershipService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsProgramLeadershipV005Test extends TestCase
{
    use RefreshDatabase;

    private function seedProgramFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Kabir Malhotra']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'student');
    }

    public function test_program_director_can_open_program_leadership_dashboard(): void
    {
        $this->seedProgramFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.index'))
            ->assertOk()
            ->assertSee('Program Leadership OS')
            ->assertSee('Program Portfolio')
            ->assertSee('Course Delivery')
            ->assertSee('Student Success')
            ->assertSee('Quality Signals')
            ->assertSee('PGDM');
    }

    public function test_program_leadership_source_lists_are_database_backed_and_linked(): void
    {
        $fixture = $this->seedProgramFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.student-success'))
            ->assertOk()
            ->assertSee('Student Success')
            ->assertSee('Filtered Source List')
            ->assertSee('Kabir Malhotra')
            ->assertDontSee('Student #'.$fixture['student']->id)
            ->assertSee(route('chair.students.at-risk'), false);

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.course-delivery'))
            ->assertOk()
            ->assertSee('Course Delivery')
            ->assertSee('Industry Immersion Lab');
    }

    public function test_program_leadership_reports_page_lists_operational_reports(): void
    {
        $this->seedProgramFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.reports'))
            ->assertOk()
            ->assertSee('Program Leadership Reports')
            ->assertSee('Program portfolio')
            ->assertSee('Course delivery')
            ->assertSee('Student success');
    }

    public function test_program_leadership_service_respects_program_scope(): void
    {
        $this->seedProgramFixture();
        $otherProgram = Program::factory()->create(['code' => 'BBA-HIDDEN', 'is_active' => true]);
        Subject::factory()->create(['program_id' => $otherProgram->id, 'name' => 'Hidden Program Leadership Subject', 'is_active' => true]);

        $leader = User::where('email', 'hod@college.com')->firstOrFail();
        $data = app(AcademicProgramLeadershipService::class)->courseDelivery($leader);
        $titles = collect($data['items'])->pluck('title');

        $this->assertFalse($titles->contains('Hidden Program Leadership Subject'));
    }

    public function test_course_coordinator_subject_scope_derives_program_leadership_visibility(): void
    {
        $this->seedProgramFixture();
        $scopedProgram = Program::factory()->create(['code' => 'SCOPED-PG', 'name' => 'Scoped Leadership Program', 'is_active' => true]);
        $scopedSubject = Subject::factory()->create([
            'program_id' => $scopedProgram->id,
            'name' => 'Scoped Leadership Subject',
            'is_active' => true,
        ]);
        $hiddenProgram = Program::factory()->create(['code' => 'BBA-HIDDEN', 'name' => 'Hidden BBA', 'is_active' => true]);
        Subject::factory()->create(['program_id' => $hiddenProgram->id, 'name' => 'Hidden Program Leadership Subject', 'is_active' => true]);

        Role::firstOrCreate(['name' => 'course_coordinator', 'guard_name' => 'web']);
        $coordinator = User::factory()->create(['name' => 'Scoped Course Coordinator']);
        $coordinator->assignRole('course_coordinator');

        AcademicScopeAssignment::create([
            'user_id' => $coordinator->id,
            'scope_type' => 'subject',
            'scope_id' => $scopedSubject->id,
            'scope_code' => $scopedSubject->code,
            'scope_name' => $scopedSubject->name,
            'context' => 'course_coordination',
            'can_manage' => true,
            'is_active' => true,
            'assigned_by' => $coordinator->id,
            'assigned_at' => now(),
        ]);

        $this->assertSame([$scopedSubject->id], app(\App\Services\AcademicScopeService::class)->scopeIdsFor($coordinator, 'subject')->all());
        $this->assertSame($scopedProgram->id, Subject::whereKey($scopedSubject->id)->value('program_id'));
        $this->assertSame(1, Program::whereKey($scopedProgram->id)->where('is_active', true)->count());

        $data = app(AcademicProgramLeadershipService::class)->programPortfolio($coordinator);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue(
            $titles->contains('Scoped Leadership Program'),
            'Program leadership titles: ' . $titles->join(', ')
        );
        $this->assertFalse($titles->contains('Hidden BBA'));
    }

    public function test_program_leadership_student_success_ignores_draft_timetable_attendance(): void
    {
        $fixture = $this->seedProgramFixture();
        $leader = User::where('email', 'chair@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $semester = Semester::where('is_current', true)->firstOrFail();
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);

        $officialStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Official Program Leadership Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);
        $draftOnlyStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft Program Leadership Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
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
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
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

        $data = app(AcademicProgramLeadershipService::class)->studentSuccess($leader);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Official Program Leadership Risk'));
        $this->assertFalse($titles->contains('Draft Program Leadership Risk'));
    }

    public function test_program_leadership_student_success_uses_only_published_results_for_weak_performance(): void
    {
        $fixture = $this->seedProgramFixture();
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        $publishedStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Published Program Weak Result'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);
        $draftStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft Program Weak Result'])->id,
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

        ExamResult::factory()->create(['exam_id' => $publishedExam->id, 'student_id' => $publishedStudent->id, 'marks_obtained' => 24]);
        ExamResult::factory()->create(['exam_id' => $draftExam->id, 'student_id' => $draftStudent->id, 'marks_obtained' => 14]);

        $data = app(AcademicProgramLeadershipService::class)->studentSuccess($leader);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Published Program Weak Result'));
        $this->assertFalse($titles->contains('Draft Program Weak Result'));
    }

    public function test_program_leadership_course_delivery_treats_draft_version_entries_as_unpublished(): void
    {
        $fixture = $this->seedProgramFixture();
        $leader = User::where('email', 'chair@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $semester = Semester::where('is_current', true)->firstOrFail();
        $term = Term::factory()->create(['program_id' => $fixture['program']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);
        $service = app(AcademicProgramLeadershipService::class);
        $before = $service->courseDelivery($leader);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $leader->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1])->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $leader->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '10:00:00', 'sort_order' => 2])->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $after = $service->courseDelivery($leader);

        $this->assertSame($before['metrics']['published_slots'] + 1, $after['metrics']['published_slots']);
        $this->assertSame($before['metrics']['draft_timetable'] + 1, $after['metrics']['draft_timetable']);
    }

    public function test_program_leadership_course_delivery_prefers_canonical_pmc_sessions_for_migrated_scope(): void
    {
        $fixture = $this->seedProgramFixture();
        $leader = User::where('email', 'chair@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $semester = Semester::where('is_current', true)->firstOrFail();
        $batch = Batch::factory()->create(['program_id' => $fixture['program']->id]);
        $term = Term::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $batch->id]);
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1]);
        $service = app(AcademicProgramLeadershipService::class);
        $before = $service->courseDelivery($leader);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Program Leadership Canonical Group',
            'group_type' => 'core_section',
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $leader->id,
            'published_by' => $leader->id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $leader->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Program Delivery Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $leader->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $publishedVersion->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $leader->id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => $group->id,
            'session_index' => 2,
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
        foreach (range(1, 3) as $day) {
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $fixture['program']->id,
                'term_id' => $term->id,
                'subject_id' => $fixture['subject']->id,
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
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 4,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $after = $service->courseDelivery($leader);
        $draftItem = collect($after['items'])->first(fn (array $item) => ($item['source'] ?? null) === 'canonical_pmc_generation_items' && $item['status'] === 'Draft version');

        $this->assertSame($before['metrics']['published_slots'] + 1, $after['metrics']['published_slots']);
        $this->assertSame($before['metrics']['draft_timetable'] + 1, $after['metrics']['draft_timetable']);
        $this->assertNotNull($draftItem);
        $this->assertSame('Draft version', $draftItem['status']);
    }

    public function test_non_academic_user_cannot_access_program_leadership_os(): void
    {
        $this->seedProgramFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.program-leadership.index'))
            ->assertForbidden();
    }
}
