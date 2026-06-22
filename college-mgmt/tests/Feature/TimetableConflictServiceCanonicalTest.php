<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\TimetableConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableConflictServiceCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_audit_allows_unrelated_parallel_groups_in_same_batch(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);

        $this->createSession($fixture, 'Elective Group A', $slot, [
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'A-101'])->id,
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);
        $this->createSession($fixture, 'Elective Group B', $slot, [
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'B-101'])->id,
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);

        $conflicts = app(TimetableConflictService::class)->auditTerm($fixture['term']->id, $fixture['batch']->id);

        $this->assertSame([], $conflicts);
    }

    public function test_canonical_audit_flags_shared_student_between_parallel_elective_groups(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $student = Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id]);

        $this->createSession($fixture, 'Elective Group A', $slot, [
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'A-102'])->id,
            'students' => [$student->id],
        ]);
        $this->createSession($fixture, 'Elective Group B', $slot, [
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'B-102'])->id,
            'students' => [$student->id],
        ]);

        $conflicts = app(TimetableConflictService::class)->auditTerm($fixture['term']->id, $fixture['batch']->id);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('Student cohort conflict', $conflicts[0]);
    }

    public function test_canonical_audit_flags_duration_overlap_for_teacher_and_room(): void
    {
        $fixture = $this->fixture();
        $slotOne = TimetableSlot::factory()->create(['sort_order' => 1]);
        $slotTwo = TimetableSlot::factory()->create(['sort_order' => 2]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create(['room_number' => 'LAB-1']);

        $this->createSession($fixture, 'Lab Group A', $slotOne, [
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'duration_slots' => 2,
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);
        $this->createSession($fixture, 'Lab Group B', $slotTwo, [
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'students' => [Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id])->id],
        ]);

        $conflicts = app(TimetableConflictService::class)->auditTerm($fixture['term']->id, $fixture['batch']->id);

        $this->assertTrue(collect($conflicts)->contains(fn (string $conflict) => str_contains($conflict, 'Teacher conflict')));
        $this->assertTrue(collect($conflicts)->contains(fn (string $conflict) => str_contains($conflict, 'Room conflict')));
    }

    public function test_canonical_audit_includes_group_only_published_sessions(): void
    {
        $fixture = $this->fixture();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $student = Student::factory()->create(['program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id]);

        $this->createSession($fixture, 'Published Group Only A', $slot, [
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'PGO-A'])->id,
            'students' => [$student->id],
            'direct_scope' => false,
            'status' => 'published',
            'official_status' => 'published',
        ]);
        $this->createSession($fixture, 'Published Group Only B', $slot, [
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['room_number' => 'PGO-B'])->id,
            'students' => [$student->id],
            'direct_scope' => false,
            'status' => 'published',
            'official_status' => 'published',
        ]);

        $conflicts = app(TimetableConflictService::class)->auditTerm($fixture['term']->id, $fixture['batch']->id);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('Student cohort conflict', $conflicts[0]);
    }

    private function fixture(): array
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Conflict Audit Run',
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

    private function createSession(array $fixture, string $groupName, TimetableSlot $slot, array $overrides = []): AcademicPmcTimetableGenerationItem
    {
        $subject = Subject::factory()->create(['program_id' => $fixture['program']->id, 'name' => $groupName . ' Subject']);
        $group = AcademicPmcCourseGroup::create([
            'name' => $groupName,
            'group_type' => str_contains($groupName, 'Lab') ? 'lab_group' : 'elective_group',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => count($overrides['students'] ?? []),
            'status' => 'active',
            'is_locked' => true,
        ]);

        foreach ($overrides['students'] ?? [] as $studentId) {
            AcademicPmcCourseGroupMember::create([
                'course_group_id' => $group->id,
                'student_id' => $studentId,
                'status' => 'active',
            ]);
        }

        $directScope = $overrides['direct_scope'] ?? true;

        return AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $fixture['run']->id,
            'timetable_version_id' => $fixture['version']->id,
            'course_group_id' => $group->id,
            'program_id' => $directScope ? $fixture['program']->id : null,
            'batch_id' => $directScope ? $fixture['batch']->id : null,
            'term_id' => $directScope ? $fixture['term']->id : null,
            'subject_id' => $directScope ? $subject->id : null,
            'session_index' => 1,
            'session_type' => str_contains($groupName, 'Lab') ? 'lab' : 'lecture',
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
