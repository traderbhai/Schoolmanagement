<?php

namespace Tests\Feature;

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
use App\Models\TeacherAvailability;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\SoftConstraintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftConstraintServiceCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_uses_canonical_pmc_sessions_for_teacher_availability(): void
    {
        $fixture = $this->fixture();
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['name' => 'Unavailable Canonical Slot', 'sort_order' => 1]);

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'term_id' => $fixture['term']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'availability' => 'unavailable',
        ]);

        $this->createCanonicalSession($fixture, $slot, [
            'teacher_id' => $teacher->id,
            'group_name' => 'Canonical Section A',
        ]);

        $audit = app(SoftConstraintService::class)->auditTermConstraints(
            $fixture['term']->id,
            $fixture['program']->id,
            $fixture['batch']->id
        );

        $this->assertContains('unavailable_time_assigned', collect($audit['issues'])->pluck('type')->all());
        $this->assertSame(1, $audit['summary']['warnings']);
    }

    public function test_audit_suppresses_stale_legacy_rows_when_canonical_scope_exists(): void
    {
        $fixture = $this->fixture();
        $canonicalSlot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $legacyBreakSlot = TimetableSlot::factory()->create(['sort_order' => 2, 'is_break' => true, 'name' => 'Legacy Break']);

        $this->createCanonicalSession($fixture, $canonicalSlot, ['group_name' => 'Canonical Section A']);

        TimetableEntry::create([
            'semester_id' => Semester::factory()->create()->id,
            'course_id' => Course::factory()->create()->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'batch_id' => $fixture['batch']->id,
            'subject_id' => Subject::factory()->create(['program_id' => $fixture['program']->id])->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $legacyBreakSlot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $audit = app(SoftConstraintService::class)->auditTermConstraints(
            $fixture['term']->id,
            $fixture['program']->id,
            $fixture['batch']->id
        );

        $this->assertNotContains('class_during_break', collect($audit['issues'])->pluck('type')->all());
    }

    public function test_audit_uses_group_only_published_canonical_sessions_and_suppresses_legacy_scope(): void
    {
        $fixture = $this->fixture();
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['name' => 'Published Group Slot', 'sort_order' => 1]);
        $legacyBreakSlot = TimetableSlot::factory()->create(['sort_order' => 2, 'is_break' => true, 'name' => 'Stale Legacy Break']);

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'term_id' => $fixture['term']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'availability' => 'unavailable',
        ]);

        $this->createCanonicalSession($fixture, $slot, [
            'teacher_id' => $teacher->id,
            'group_name' => 'Group Only Published Section',
            'direct_scope' => false,
            'status' => 'published',
            'official_status' => 'published',
        ]);

        TimetableEntry::create([
            'semester_id' => Semester::factory()->create()->id,
            'course_id' => Course::factory()->create()->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $fixture['term']->id,
            'batch_id' => $fixture['batch']->id,
            'subject_id' => Subject::factory()->create(['program_id' => $fixture['program']->id])->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $legacyBreakSlot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $audit = app(SoftConstraintService::class)->auditTermConstraints(
            $fixture['term']->id,
            $fixture['program']->id,
            $fixture['batch']->id
        );

        $issueTypes = collect($audit['issues'])->pluck('type')->all();

        $this->assertContains('unavailable_time_assigned', $issueTypes);
        $this->assertNotContains('class_during_break', $issueTypes);
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
            'title' => 'Soft Constraint Canonical Run',
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

    private function createCanonicalSession(array $fixture, TimetableSlot $slot, array $overrides = []): AcademicPmcTimetableGenerationItem
    {
        $subject = Subject::factory()->create(['program_id' => $fixture['program']->id]);
        $group = AcademicPmcCourseGroup::create([
            'name' => $overrides['group_name'] ?? 'Canonical Group',
            'group_type' => 'core_section',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 30,
            'status' => 'active',
            'is_locked' => true,
        ]);

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
            'session_type' => $overrides['session_type'] ?? 'lecture',
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
