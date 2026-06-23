<?php

namespace Tests\Unit;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Services\CanonicalSessionOperationsService;
use App\Services\TimetableCohortAuditService;
use App\Services\TimetableLaunchReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableCanonicalCompletionServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_readiness_blocks_when_groups_and_faculty_are_missing(): void
    {
        $fixture = $this->baseFixture();

        $readiness = app(TimetableLaunchReadinessService::class)->evaluate(null, [
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ]);

        $this->assertSame('blocked', $readiness['status']);
        $this->assertContains('groups', collect($readiness['hard_blockers'])->pluck('key')->all());
    }

    public function test_generation_gate_allows_warning_only_readiness(): void
    {
        $fixture = $this->baseFixture();
        $this->readyGroupFixture($fixture);

        $service = app(TimetableLaunchReadinessService::class);
        $readiness = $service->evaluate(null, [
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ]);

        $this->assertSame('warning', $readiness['status']);
        $this->assertSame([], $service->generationBlockers(null, [
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ]));
    }

    public function test_cohort_audit_detects_overlapping_students_and_elective_clashes(): void
    {
        $fixture = $this->baseFixture();
        [$left, $right] = $this->readyGroupFixture($fixture, 'elective_group');
        $student = Student::factory()->create([
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'status' => 'active',
        ]);
        AcademicPmcCourseGroupMember::create(['course_group_id' => $left->id, 'student_id' => $student->id, 'status' => 'active']);
        AcademicPmcCourseGroupMember::create(['course_group_id' => $right->id, 'student_id' => $student->id, 'status' => 'active']);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Audit run',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'generated',
        ]);

        foreach ([$left, $right] as $group) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $group->id,
                'program_id' => $fixture['program']->id,
                'batch_id' => $fixture['batch']->id,
                'term_id' => $fixture['term']->id,
                'subject_id' => $fixture['subject']->id,
                'teacher_id' => $fixture['teacher']->id,
                'classroom_id' => $fixture['room']->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $fixture['slot']->id,
                'status' => 'scheduled',
            ]);
        }

        $audit = app(TimetableCohortAuditService::class)->audit([
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $audit['overlapping_group_students']['count']);
        $this->assertSame('blocked', $audit['elective_clash_matrix']['rows'][0]['status']);
    }

    public function test_canonical_session_detail_prefers_generation_item_identity(): void
    {
        $fixture = $this->baseFixture();
        [$group] = $this->readyGroupFixture($fixture);
        $student = Student::factory()->create([
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'status' => 'active',
        ]);
        AcademicPmcCourseGroupMember::create(['course_group_id' => $group->id, 'student_id' => $student->id, 'status' => 'active']);
        $run = AcademicPmcTimetableGenerationRun::create(['title' => 'Detail run', 'program_id' => $fixture['program']->id, 'batch_id' => $fixture['batch']->id, 'term_id' => $fixture['term']->id]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => $fixture['room']->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $fixture['slot']->id,
            'status' => 'scheduled',
            'metadata' => ['solver_pass' => 'regular_lectures', 'candidate_score' => 88],
        ]);

        $detail = app(CanonicalSessionOperationsService::class)->detail($item);

        $this->assertSame($item->id, $detail['item']->id);
        $this->assertSame(1, $detail['member_count']);
        $this->assertSame('missing', $detail['bridge']['status']);
        $this->assertSame('regular_lectures', $detail['solver']['pass']);
    }

    private function baseFixture(): array
    {
        $department = Department::factory()->create(['code' => 'TT']);
        $program = Program::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'is_active' => true]);
        $teacher = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $room = Classroom::factory()->create(['is_active' => true, 'capacity' => 80]);
        $slot = TimetableSlot::factory()->create(['is_active' => true, 'is_break' => false, 'sort_order' => 1]);

        return compact('department', 'program', 'batch', 'term', 'subject', 'teacher', 'room', 'slot');
    }

    private function readyGroupFixture(array $fixture, string $groupType = 'core_section'): array
    {
        $left = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Group A',
            'group_type' => $groupType,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 1,
            'status' => 'ready',
            'is_locked' => true,
        ]);
        $right = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Group B',
            'group_type' => $groupType,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 1,
            'status' => 'ready',
            'is_locked' => true,
        ]);

        foreach ([$left, $right] as $group) {
            AcademicPmcGroupFacultyAssignment::create([
                'course_group_id' => $group->id,
                'teacher_id' => $fixture['teacher']->id,
                'assignment_role' => 'primary',
                'approval_status' => 'pmc_approved',
            ]);
        }

        return [$left, $right];
    }
}
