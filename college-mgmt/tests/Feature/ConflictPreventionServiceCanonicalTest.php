<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\ConflictPreventionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictPreventionServiceCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_availability_allows_unrelated_canonical_group_in_same_batch(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);

        $this->createSession($fixture, 'Group A', $slot, [
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);
        $candidateGroup = $this->createGroup($fixture, 'Group B', [
            Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id,
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            Teacher::factory()->create()->id,
            Classroom::factory()->create()->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertTrue($availability['available']);
        $this->assertSame([], $availability['conflicts']);
    }

    public function test_slot_availability_blocks_canonical_group_with_overlapping_student(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $student = Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id]);

        $this->createSession($fixture, 'Group A', $slot, ['students' => [$student->id]]);
        $candidateGroup = $this->createGroup($fixture, 'Group B', [$student->id]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            Teacher::factory()->create()->id,
            Classroom::factory()->create()->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Student group has an overlapping class at this time', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_duration_overlap_for_teacher_and_room(): void
    {
        $fixture = $this->fixture();
        $slotOne = TimetableSlot::factory()->create(['sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['sort_order' => 2]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create();
        $candidateGroup = $this->createGroup($fixture, 'Group B', [
            Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id,
        ]);

        $this->createSession($fixture, 'Group A', $slotOne, [
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'duration_slots' => 2,
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slotTwo->id,
            $teacher->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Teacher is already assigned to another class at this time', $availability['conflicts']);
        $this->assertContains('Classroom is already booked at this time', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_group_only_published_canonical_resource_conflicts(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create();
        $candidateGroup = $this->createGroup($fixture, 'Candidate Group', [
            Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id,
        ]);

        $this->createSession($fixture, 'Group Only Official', $slot, [
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'direct_scope' => false,
            'status' => 'published',
            'official_status' => 'published',
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            $teacher->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Teacher is already assigned to another class at this time', $availability['conflicts']);
        $this->assertContains('Classroom is already booked at this time', $availability['conflicts']);
        $this->assertNotContains('Student group has an overlapping class at this time', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_group_only_published_canonical_student_overlap(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $student = Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id]);
        $candidateGroup = $this->createGroup($fixture, 'Candidate Shared Student Group', [$student->id]);

        $this->createSession($fixture, 'Group Only Shared Official', $slot, [
            'direct_scope' => false,
            'status' => 'published',
            'official_status' => 'published',
            'students' => [$student->id],
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            Teacher::factory()->create()->id,
            Classroom::factory()->create()->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Student group has an overlapping class at this time', $availability['conflicts']);
    }

    public function test_slot_availability_does_not_ignore_published_canonical_session_finality(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create();

        $published = $this->createSession($fixture, 'Published Finality Group', $slot, [
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'status' => 'published',
            'official_status' => 'published',
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            $teacher->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $published->course_group_id,
            1,
            [$published->id]
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Published or locked canonical timetable session cannot be edited directly', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_undersized_room_for_canonical_group(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $candidateGroup = $this->createGroup($fixture, 'Large Candidate Group', []);
        $candidateGroup->update(['current_strength' => 45]);
        $room = Classroom::factory()->create(['capacity' => 30]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            Teacher::factory()->create()->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Classroom capacity is too small for this student group (45 students)', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_lab_group_in_non_lab_room(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $candidateGroup = $this->createGroup($fixture, 'Lab Candidate Group', []);
        $candidateGroup->update(['group_type' => 'lab_group']);
        $room = Classroom::factory()->create(['capacity' => 60, 'type' => 'lecture', 'has_lab' => false]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            Teacher::factory()->create()->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Lab/practical group requires a lab-capable room', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_teacher_marked_unavailable(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $teacher = Teacher::factory()->create();
        $candidateGroup = $this->createGroup($fixture, 'Unavailable Teacher Group', []);

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'term_id' => $fixture['term']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'availability' => 'unavailable',
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            $teacher->id,
            Classroom::factory()->create(['capacity' => 60])->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Teacher is marked unavailable at this time', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_teacher_outside_available_days(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $teacher = Teacher::factory()->create();
        $candidateGroup = $this->createGroup($fixture, 'Available Day Group', []);

        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'term_id' => $fixture['term']->id,
            'faculty_type' => 'regular',
            'available_days' => [2, 3],
            'preferred_slots' => [],
            'unavailable_slots' => [],
            'max_classes_per_day' => 4,
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            $teacher->id,
            Classroom::factory()->create(['capacity' => 60])->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Teacher is not available on this day', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_teacher_preference_unavailable_covered_slot(): void
    {
        $fixture = $this->fixture();
        $slotOne = TimetableSlot::factory()->create(['sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['sort_order' => 2]);
        $teacher = Teacher::factory()->create();
        $candidateGroup = $this->createGroup($fixture, 'Covered Slot Preference Group', []);

        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'term_id' => $fixture['term']->id,
            'faculty_type' => 'regular',
            'available_days' => [1],
            'preferred_slots' => [],
            'unavailable_slots' => [['day' => 1, 'slot_id' => $slotTwo->id]],
            'max_classes_per_day' => 4,
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slotOne->id,
            $teacher->id,
            Classroom::factory()->create(['capacity' => 60])->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id,
            2
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Teacher preference marks this slot unavailable', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_teacher_preference_unavailable_day_slot_map(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $teacher = Teacher::factory()->create();
        $candidateGroup = $this->createGroup($fixture, 'Mapped Preference Group', []);

        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'term_id' => $fixture['term']->id,
            'faculty_type' => 'adjunct',
            'available_days' => [1, 2],
            'preferred_slots' => [],
            'unavailable_slots' => [1 => [$slot->id]],
            'max_classes_per_day' => 2,
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            $teacher->id,
            Classroom::factory()->create(['capacity' => 60])->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Teacher preference marks this slot unavailable', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_room_hard_lock_on_covered_slot(): void
    {
        $fixture = $this->fixture();
        $slotOne = TimetableSlot::factory()->create(['sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['sort_order' => 2]);
        $room = Classroom::factory()->create(['capacity' => 60]);
        $candidateGroup = $this->createGroup($fixture, 'Room Lock Candidate Group', []);

        AcademicPmcLockedSlot::create([
            'title' => 'Auditorium maintenance',
            'slot_type' => 'room_unavailable',
            'term_id' => $fixture['term']->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotTwo->id,
            'is_hard_lock' => true,
            'status' => 'active',
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slotOne->id,
            Teacher::factory()->create()->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id,
            2
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Classroom has a hard locked slot at this time', $availability['conflicts']);
    }

    public function test_slot_availability_blocks_batch_institutional_hard_lock(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $candidateGroup = $this->createGroup($fixture, 'Batch Lock Candidate Group', []);

        AcademicPmcLockedSlot::create([
            'title' => 'Batch orientation',
            'slot_type' => 'orientation',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'is_hard_lock' => true,
            'status' => 'active',
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            Teacher::factory()->create()->id,
            Classroom::factory()->create(['capacity' => 60])->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertFalse($availability['available']);
        $this->assertContains('Institutional slot is hard locked for this scope', $availability['conflicts']);
    }

    public function test_slot_availability_allows_matching_course_group_hard_lock(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create(['capacity' => 60]);
        $candidateGroup = $this->createGroup($fixture, 'Exact Lock Candidate Group', []);

        AcademicPmcLockedSlot::create([
            'title' => 'Fixed lab placement',
            'slot_type' => 'faculty_fixed',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'course_group_id' => $candidateGroup->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'is_hard_lock' => true,
            'status' => 'active',
        ]);

        $availability = app(ConflictPreventionService::class)->isSlotAvailable(
            1,
            $slot->id,
            $teacher->id,
            $room->id,
            $fixture['batch']->id,
            $fixture['term']->id,
            $candidateGroup->id
        );

        $this->assertTrue($availability['available']);
        $this->assertSame([], $availability['conflicts']);
    }

    private function fixture(): array
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Prevention Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'draft',
            'scheduled_count' => 0,
            'quality_score' => 0,
        ]);

        return compact('program', 'batch', 'term', 'version', 'run');
    }

    private function createGroup(array $fixture, string $name, array $studentIds): AcademicPmcCourseGroup
    {
        $subject = Subject::factory()->create(['program_id' => $fixture['program']->id]);
        $group = AcademicPmcCourseGroup::create([
            'name' => $name,
            'group_type' => 'elective_group',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => count($studentIds),
            'status' => 'active',
            'is_locked' => true,
        ]);

        foreach ($studentIds as $studentId) {
            AcademicPmcCourseGroupMember::create([
                'course_group_id' => $group->id,
                'student_id' => $studentId,
                'status' => 'active',
            ]);
        }

        return $group;
    }

    private function createSession(array $fixture, string $groupName, TimetableSlot $slot, array $overrides = []): AcademicPmcTimetableGenerationItem
    {
        $group = $this->createGroup($fixture, $groupName, $overrides['students'] ?? []);

        $directScope = $overrides['direct_scope'] ?? true;

        return AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $fixture['run']->id,
            'timetable_version_id' => $fixture['version']->id,
            'course_group_id' => $group->id,
            'program_id' => $directScope ? $fixture['program']->id : null,
            'batch_id' => $directScope ? $fixture['batch']->id : null,
            'term_id' => $directScope ? $fixture['term']->id : null,
            'subject_id' => $directScope ? $group->subject_id : null,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => $overrides['duration_slots'] ?? 1,
            'teacher_id' => $overrides['teacher_id'] ?? Teacher::factory()->create()->id,
            'classroom_id' => $overrides['classroom_id'] ?? Classroom::factory()->create()->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => $overrides['status'] ?? 'scheduled',
            'official_status' => $overrides['official_status'] ?? 'draft',
            'source_type' => 'generated',
        ]);
    }
}
