<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Services\AutoSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoSchedulingServiceCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_schedule_uses_course_groups_and_allows_parallel_groups_in_same_batch(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id, 'intake_capacity' => 60]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id]);
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        Classroom::factory()->create(['capacity' => 30, 'room_number' => 'CG-101']);
        Classroom::factory()->create(['capacity' => 30, 'room_number' => 'CG-102']);

        $groupA = $this->courseGroup($program, $batch, $term, 'Elective A');
        $groupB = $this->courseGroup($program, $batch, $term, 'Elective B');

        $teacherA = Teacher::factory()->create();
        $teacherB = Teacher::factory()->create();
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $groupA->id,
            'teacher_id' => $teacherA->id,
            'assignment_role' => 'primary',
        ]);
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $groupB->id,
            'teacher_id' => $teacherB->id,
            'assignment_role' => 'primary',
        ]);

        foreach ([$groupA, $groupB] as $group) {
            $student = Student::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id]);
            AcademicPmcCourseGroupMember::create([
                'course_group_id' => $group->id,
                'student_id' => $student->id,
                'status' => 'active',
            ]);
        }

        $result = app(AutoSchedulingService::class)->suggestSchedule($program->id, $term->id, $batch->id);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['suggestions']);
        $this->assertSame([$slot->id], collect($result['suggestions'])->pluck('timetable_slot_id')->unique()->values()->all());
        $this->assertSame(
            [$groupA->id, $groupB->id],
            collect($result['suggestions'])->pluck('course_group_id')->sort()->values()->all()
        );
        $this->assertSame(
            ['canonical_pmc_course_group'],
            collect($result['suggestions'])->pluck('source')->unique()->values()->all()
        );
    }

    public function test_suggest_schedule_reserves_all_covered_slots_for_lab_groups(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id, 'intake_capacity' => 60]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id]);
        foreach (range(1, 4) as $order) {
            TimetableSlot::factory()->create(['sort_order' => $order, 'is_break' => false]);
        }
        Classroom::factory()->create(['capacity' => 30, 'room_number' => 'LAB-101', 'type' => 'lab']);
        Classroom::factory()->create(['capacity' => 30, 'room_number' => 'LAB-102', 'type' => 'lab']);

        $teacher = Teacher::factory()->create();
        $groupA = $this->courseGroup($program, $batch, $term, 'Lab Group A', [
            'group_type' => 'lab_group',
            'constraints' => ['session_mix' => ['lab' => ['sessions' => 1, 'duration_slots' => 2]]],
        ]);
        $groupB = $this->courseGroup($program, $batch, $term, 'Lab Group B', [
            'group_type' => 'lab_group',
            'constraints' => ['session_mix' => ['lab' => ['sessions' => 1, 'duration_slots' => 2]]],
        ]);

        foreach ([$groupA, $groupB] as $group) {
            AcademicPmcGroupFacultyAssignment::create([
                'course_group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'assignment_role' => 'primary',
            ]);

            AcademicPmcCourseGroupMember::create([
                'course_group_id' => $group->id,
                'student_id' => Student::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id])->id,
                'status' => 'active',
            ]);
        }

        $result = app(AutoSchedulingService::class)->suggestSchedule($program->id, $term->id, $batch->id);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['suggestions']);
        $this->assertSame([2], collect($result['suggestions'])->pluck('duration_slots')->unique()->values()->all());

        $slotOrders = TimetableSlot::whereIn('id', collect($result['suggestions'])->pluck('timetable_slot_id'))
            ->pluck('sort_order', 'id');
        $coveredBySuggestion = collect($result['suggestions'])
            ->map(fn (array $suggestion) => [
                'day' => $suggestion['day_of_week'],
                'covered' => range(
                    (int) $slotOrders[$suggestion['timetable_slot_id']],
                    (int) $slotOrders[$suggestion['timetable_slot_id']] + (int) $suggestion['duration_slots'] - 1
                ),
            ]);

        $this->assertFalse($coveredBySuggestion[0]['day'] === $coveredBySuggestion[1]['day']
            && collect($coveredBySuggestion[0]['covered'])->intersect($coveredBySuggestion[1]['covered'])->isNotEmpty());
    }

    private function courseGroup(Program $program, Batch $batch, Term $term, string $name, array $overrides = []): AcademicPmcCourseGroup
    {
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => $name . ' Subject']);

        return AcademicPmcCourseGroup::create([
            'name' => $name,
            'group_type' => $overrides['group_type'] ?? 'elective_group',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 30,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
            'constraints' => $overrides['constraints'] ?? null,
        ]);
    }
}
