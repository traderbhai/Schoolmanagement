<?php

namespace Tests\Unit;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableServiceTest extends TestCase
{
    use RefreshDatabase;

    private TimetableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TimetableService();
    }

    public function test_no_conflicts_returns_empty_array(): void
    {
        // Use a semester_id/day/slot that won't exist in a fresh test DB
        $data = [
            'semester_id'       => 9999,
            'day_of_week'       => 1,
            'timetable_slot_id' => 9999,
            'classroom_id'      => 9999,
            'teacher_id'        => 9999,
            'course_id'         => 9999,
        ];
        $conflicts = $this->service->checkConflicts($data);
        $this->assertSame([], $conflicts);
    }

    public function test_checkConflicts_allows_same_course_parallel_section_when_resources_differ(): void
    {
        $semester = Semester::factory()->create();
        $course = Course::factory()->create();
        $slot = TimetableSlot::factory()->create();

        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);

        $conflicts = $this->service->checkConflicts([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
        ]);

        $this->assertSame([], $conflicts);
    }

    public function test_checkConflicts_blocks_official_canonical_pmc_teacher_and_room_overlap(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $slotOne = TimetableSlot::factory()->create(['sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['sort_order' => 2]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create(['room_number' => 'CAN-CONFLICT-101']);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Conflict Check Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Conflict Group',
            'group_type' => 'lab_group',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 30,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 1,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotOne->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);

        $conflicts = $this->service->checkConflicts([
            'semester_id' => $semester->id,
            'course_id' => Course::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slotTwo->id,
            'day_of_week' => 1,
        ]);

        $this->assertContains('Room conflict: CAN-CONFLICT-101 is already booked for an official PMC session at this slot.', $conflicts);
        $this->assertTrue(collect($conflicts)->contains(fn (string $conflict) => str_contains($conflict, 'is already teaching an official PMC session')));
    }

    public function test_buildWeeklyGrid_returns_array(): void
    {
        $grid = $this->service->buildWeeklyGrid(999);
        $this->assertIsArray($grid);
        foreach (range(1, 6) as $day) {
            $this->assertArrayHasKey($day, $grid);
        }
    }

    public function test_buildWeeklyGrid_preserves_parallel_canonical_official_sessions(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Parallel Admin Report Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 2,
            'quality_score' => 100,
        ]);

        foreach (['Section A', 'Section B'] as $index => $groupName) {
            $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Parallel Subject ' . $groupName]);
            $group = AcademicPmcCourseGroup::create([
                'name' => $groupName,
                'group_type' => 'core_section',
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'min_capacity' => 1,
                'max_capacity' => 60,
                'current_strength' => 30,
                'status' => 'active',
                'is_locked' => true,
            ]);

            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'timetable_version_id' => $version->id,
                'course_group_id' => $group->id,
                'session_index' => $index + 1,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => Teacher::factory()->create()->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $slot->id,
                'status' => $index === 0 ? 'locked' : 'published',
                'official_status' => 'published',
                'source_type' => 'generated',
                'published_at' => now(),
                'published_by' => $version->published_by,
            ]);
        }

        $grid = $this->service->buildWeeklyGrid($semester->id, officialOnly: true);

        $this->assertCount(2, $grid[1][$slot->id]);
        $this->assertSame(['Section A', 'Section B'], $grid[1][$slot->id]->pluck('course_group.name')->sort()->values()->all());
        $this->assertSame(
            ['canonical_pmc_official_session'],
            $grid[1][$slot->id]->pluck('source')->unique()->values()->all()
        );
    }

    public function test_classroom_utilization_counts_canonical_pmc_official_sessions(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $room = Classroom::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['sort_order' => 2]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Room Utilization Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Room Utilization Group',
            'group_type' => 'lab_group',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 30,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);

        $this->assertSame(16.7, $this->service->getClassroomUtilization($room->id, $semester->id));

        $grid = $this->service->buildWeeklyGrid($semester->id, officialOnly: true);
        $this->assertSame('Room Utilization Group', $grid[1][$slot->id]->first()->course_group->name);
        $this->assertSame('Room Utilization Group', $grid[1][$slotTwo->id]->first()->course_group->name);
        $this->assertFalse($grid[1][$slot->id]->first()->is_continuation);
        $this->assertTrue($grid[1][$slotTwo->id]->first()->is_continuation);
    }

    public function test_teacher_weekly_load_counts_canonical_pmc_official_duration_slots(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $teacher = Teacher::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Teacher Load Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Teacher Load Group',
            'group_type' => 'lab_group',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 30,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lab',
            'duration_slots' => 3,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);

        $this->assertSame(3, $this->service->getTeacherWeeklyLoad($teacher->id, $semester->id));
    }
}
