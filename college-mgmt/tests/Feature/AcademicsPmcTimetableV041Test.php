<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
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
use App\Services\AcademicPmcTimetableV041Service;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsPmcTimetableV041Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v041 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);
        TimetableSlot::firstOrCreate(['name' => 'Fixture Period 1'], ['start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 1, 'is_active' => true]);
        Classroom::firstOrCreate(['room_number' => 'FIX-101'], ['name' => 'Fixture Room', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    private function createParallelTimetableFixture(User $actor, array $overrides = []): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $batch->id)->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $departmentId = $program->department_id ?: Department::query()->value('id');

        $slotBaseOrder = TimetableSlot::max('sort_order') + 10;
        $slotOne = TimetableSlot::create([
            'name' => 'Parallel Fixture Period 1 ' . $suffix,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_break' => false,
            'sort_order' => $slotBaseOrder,
            'is_active' => true,
        ]);
        $slotTwo = TimetableSlot::create([
            'name' => 'Parallel Fixture Period 2 ' . $suffix,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_break' => false,
            'sort_order' => $slotBaseOrder + 1,
            'is_active' => true,
        ]);

        $teacherOne = Teacher::factory()->create(['department_id' => $departmentId]);
        $teacherTwo = ($overrides['same_teacher'] ?? false)
            ? $teacherOne
            : Teacher::factory()->create(['department_id' => $departmentId]);

        $roomOne = Classroom::create([
            'room_number' => 'PAR-101-' . $suffix,
            'name' => 'Parallel Room 101 ' . $suffix,
            'capacity' => 60,
            'type' => 'lecture',
            'is_active' => true,
        ]);
        $roomTwo = ($overrides['same_room'] ?? false)
            ? $roomOne
            : Classroom::create([
                'room_number' => 'PAR-102-' . $suffix,
                'name' => 'Parallel Room 102 ' . $suffix,
                'capacity' => 60,
                'type' => 'lecture',
                'is_active' => true,
            ]);

        $groupOne = AcademicPmcCourseGroup::create([
            'name' => 'Parallel Core Section A ' . $suffix,
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $groupTwo = AcademicPmcCourseGroup::create([
            'name' => 'Parallel Core Section B ' . $suffix,
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $studentOne = Student::factory()->create([
            'department_id' => $departmentId,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $studentTwo = ($overrides['shared_student'] ?? false)
            ? $studentOne
            : Student::factory()->create([
                'department_id' => $departmentId,
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'status' => 'active',
            ]);

        $groupOne->members()->create(['student_id' => $studentOne->id, 'status' => 'active']);
        $groupTwo->members()->create(['student_id' => $studentTwo->id, 'status' => 'active']);

        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $groupOne->id,
            'teacher_id' => $teacherOne->id,
            'assignment_role' => 'primary',
            'weekly_hours' => 3,
        ]);
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $groupTwo->id,
            'teacher_id' => $teacherTwo->id,
            'assignment_role' => 'primary',
            'weekly_hours' => 3,
        ]);

        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => $overrides['title'] ?? 'Parallel Timetable Fixture',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'created_by' => $actor->id,
            'status' => 'completed',
            'scheduled_count' => 2,
            'quality_score' => 100,
        ]);

        $durationOne = $overrides['first_duration'] ?? 1;
        $secondSlot = ($overrides['second_slot_offset'] ?? 0) === 1 ? $slotTwo : $slotOne;

        $itemOne = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupOne->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => $durationOne,
            'teacher_id' => $teacherOne->id,
            'classroom_id' => $roomOne->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotOne->id,
            'status' => 'scheduled',
            'confidence' => 95,
        ]);
        $itemTwo = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupTwo->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacherTwo->id,
            'classroom_id' => $roomTwo->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $secondSlot->id,
            'status' => 'scheduled',
            'confidence' => 95,
        ]);

        return compact('program', 'batch', 'term', 'subject', 'slotOne', 'slotTwo', 'teacherOne', 'teacherTwo', 'roomOne', 'roomTwo', 'groupOne', 'groupTwo', 'studentOne', 'studentTwo', 'run', 'itemOne', 'itemTwo');
    }

    private function createGeneratorHardLockFixture(string $prefix): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $batch->id)->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $departmentId = $program->department_id ?: Department::query()->value('id');

        $slot = TimetableSlot::create([
            'name' => $prefix . ' Slot ' . $suffix,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_break' => false,
            'sort_order' => TimetableSlot::max('sort_order') + 20,
            'is_active' => true,
        ]);

        $teacher = Teacher::factory()->create(['department_id' => $departmentId]);
        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'term_id' => $term->id,
            'faculty_type' => 'regular',
            'available_days' => [1],
            'preferred_slots' => [$slot->id],
            'unavailable_slots' => [],
            'max_classes_per_day' => 4,
            'max_consecutive_classes' => 3,
            'max_weekly_load' => 18,
        ]);

        $group = AcademicPmcCourseGroup::create([
            'name' => $prefix . ' Group ' . $suffix,
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 10,
            'status' => 'active',
            'is_locked' => true,
            'constraints' => ['weekly_sessions' => 1],
        ]);

        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'weekly_hours' => 1,
        ]);

        return compact('program', 'batch', 'term', 'subject', 'slot', 'teacher', 'group');
    }

    public function test_dean_and_pmc_can_open_v041_timetable_pages(): void
    {
        $chair = $this->seedFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        foreach ([$chair, $dean] as $user) {
            foreach ([
                'academics.pmc.timetable-os.index' => 'PMC Timetable OS',
                'academics.pmc.course-allocation.index' => 'PMC Course Allocation',
                'academics.pmc.elective-allocation.index' => 'PMC Course Allocation',
                'academics.pmc.student-course-baskets.index' => 'PMC Student Course Baskets',
                'academics.pmc.course-groups.index' => 'PMC Section And Group Builder',
                'academics.pmc.section-faculty-allocation.index' => 'PMC Section/Group Faculty And Load Planning',
                'academics.pmc.locked-slots.index' => 'PMC Locked Slots And Timetable Readiness',
                'academics.pmc.timetable-generator.index' => 'PMC Constraint-Based Timetable Generator',
                'academics.pmc.timetable-planner.index' => 'PMC Timetable Planning Board',
                'academics.pmc.timetable-versions-v041.index' => 'PMC Timetable Version, Freeze And Impact',
                'academics.pmc.substitution-intelligence.index' => 'PMC Substitution And Change Intelligence',
                'academics.pmc.timetable-reports.index' => 'PMC Timetable Reports And Notifications',
            ] as $route => $text) {
                $this->actingAs($user)->get(route($route))->assertOk()->assertSee($text);
            }
        }
    }

    public function test_v041_dashboard_exposes_actionable_real_world_timetable_sequence(): void
    {
        $chair = $this->seedFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('aria-label="PMC timetable build workflow links"', false)
            ->assertSee(route('academics.pmc.student-course-baskets.index'), false)
            ->assertSee(route('academics.pmc.course-groups.index'), false)
            ->assertSee(route('academics.pmc.section-faculty-allocation.index'), false)
            ->assertSee(route('academics.pmc.locked-slots.index'), false)
            ->assertSee(route('academics.pmc.timetable-generator.index'), false)
            ->assertSee(route('academics.pmc.timetable-versions-v041.index'), false)
            ->assertSee('6. Approve, publish, freeze');
    }

    public function test_v041_group_launch_diagnostics_respect_pmc_manager_scope(): void
    {
        $this->seedFixture();
        $manager = User::where('email', 'pmc.manager@college.com')->firstOrFail();
        $service = app(\App\Services\AcademicPmcTimetableV041Service::class);

        $before = $service->dashboard($manager)['groupDiagnostics'];
        $department = Department::factory()->create(['code' => 'OUT', 'name' => 'Out Of Scope Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'OUT-PGDM', 'name' => 'Out Scope PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'OUT-26', 'name' => 'Out Scope 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Out Scope Term', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'OUT401', 'name' => 'Out Scope Analytics', 'credits' => 3, 'is_active' => true]);

        AcademicPmcCourseGroup::create([
            'name' => 'Out Scope Unstaffed Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 20,
            'max_capacity' => 60,
            'current_strength' => 0,
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $after = $service->dashboard($manager)['groupDiagnostics'];

        $this->assertSame($before['total_groups'], $after['total_groups']);
        $this->assertSame($before['blocker_total'], $after['blocker_total']);
    }
    public function test_v041_pages_render_operational_labels_instead_of_raw_id_fallbacks(): void
    {
        $chair = $this->seedFixture();

        foreach ([
            'academics.pmc.course-allocation.index',
            'academics.pmc.student-course-baskets.index',
            'academics.pmc.course-groups.index',
            'academics.pmc.section-faculty-allocation.index',
            'academics.pmc.locked-slots.index',
            'academics.pmc.substitution-intelligence.index',
            'academics.pmc.official-timetable.index',
            'academics.pmc.timetable-versions-v041.index',
        ] as $route) {
            $response = $this->actingAs($chair)->get(route($route))->assertOk();

            foreach (['Student #', 'Teacher #', 'Faculty #', 'Subject #', 'Term #', 'Room #', 'Slot #'] as $rawFallback) {
                $response->assertDontSee($rawFallback);
            }

            $response->assertDontSee('<td>#', false);
        }
    }

    public function test_v041_generation_blocks_when_selected_scope_has_no_course_groups(): void
    {
        $chair = $this->seedFixture();
        $department = Department::factory()->create(['code' => 'EMPTY', 'name' => 'Empty Scope Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'EMPTY-PGDM', 'name' => 'Empty Scope PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'EMPTY-26', 'name' => 'Empty Scope 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Empty Scope Term', 'is_current' => true]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'Empty Scope Generator Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertStatus(422);

        $this->assertFalse(AcademicPmcTimetableGenerationRun::where('title', 'Empty Scope Generator Run')->exists());
    }
    public function test_v041_batch_specific_generation_excludes_other_batch_groups(): void
    {
        $chair = $this->seedFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $selectedBatch = Batch::where('program_id', $program->id)->firstOrFail();
        $otherBatch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-OTHER', 'name' => 'PGDM Other Batch', 'status' => 'active']);
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $selectedBatch->id)->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();

        $selectedGroup = AcademicPmcCourseGroup::create([
            'name' => 'Selected Batch Generator Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $selectedBatch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 10,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $otherGroup = AcademicPmcCourseGroup::create([
            'name' => 'Other Batch Generator Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $otherBatch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 10,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $teacher = Teacher::firstOrFail();
        foreach ([$selectedGroup, $otherGroup] as $group) {
            AcademicPmcGroupFacultyAssignment::create([
                'course_group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'assignment_role' => 'primary',
                'assignment_source' => 'pmc',
                'approval_status' => 'pmc_approved',
                'weekly_hours' => 1,
                'assigned_by' => $chair->id,
            ]);
        }

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'Batch Scoped Generator Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $selectedBatch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'Batch Scoped Generator Run')->firstOrFail();

        $this->assertTrue(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('course_group_id', $selectedGroup->id)->exists());
        $this->assertFalse(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('course_group_id', $otherGroup->id)->exists());
    }

    public function test_v041_generator_avoids_room_hard_locks_for_candidate_placements(): void
    {
        $chair = $this->seedFixture();
        $fixture = $this->createGeneratorHardLockFixture('Room Lock Generator');
        $lockedRoom = Classroom::create([
            'room_number' => 'GEN-LOCK-R',
            'name' => 'Generator Locked Room',
            'capacity' => 20,
            'type' => 'lecture',
            'is_active' => true,
        ]);
        Classroom::create([
            'room_number' => 'GEN-OPEN-R',
            'name' => 'Generator Open Room',
            'capacity' => 60,
            'type' => 'lecture',
            'is_active' => true,
        ]);

        AcademicPmcLockedSlot::create([
            'title' => 'Generator room unavailable',
            'slot_type' => 'room_unavailable',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'classroom_id' => $lockedRoom->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $fixture['slot']->id,
            'is_hard_lock' => true,
            'status' => 'active',
            'created_by' => $chair->id,
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'Room Lock Generator Run',
            'strategy' => 'room_optimized',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'Room Lock Generator Run')->firstOrFail();

        $this->assertFalse(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->where('classroom_id', $lockedRoom->id)
            ->where('day_of_week', 1)
            ->where('timetable_slot_id', $fixture['slot']->id)
            ->exists());
    }

    public function test_v041_generator_avoids_batch_institutional_hard_locks(): void
    {
        $chair = $this->seedFixture();
        $fixture = $this->createGeneratorHardLockFixture('Batch Lock Generator');
        Classroom::create([
            'room_number' => 'GEN-BATCH-R',
            'name' => 'Generator Batch Room',
            'capacity' => 60,
            'type' => 'lecture',
            'is_active' => true,
        ]);

        AcademicPmcLockedSlot::create([
            'title' => 'Generator batch orientation',
            'slot_type' => 'orientation',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $fixture['slot']->id,
            'is_hard_lock' => true,
            'status' => 'active',
            'created_by' => $chair->id,
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'Batch Lock Generator Run',
            'strategy' => 'student_compact',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'Batch Lock Generator Run')->firstOrFail();

        $this->assertFalse(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->where('day_of_week', 1)
            ->where('timetable_slot_id', $fixture['slot']->id)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->exists());
    }

    public function test_v041_generator_records_actionable_unscheduled_diagnostics(): void
    {
        $chair = $this->seedFixture();
        $fixture = $this->createGeneratorHardLockFixture('No Room Diagnostic Generator');
        $fixture['group']->update([
            'current_strength' => 9999,
            'max_capacity' => 10000,
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'No Room Diagnostic Generator Run',
            'strategy' => 'room_optimized',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'No Room Diagnostic Generator Run')->firstOrFail();
        $item = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->where('course_group_id', $fixture['group']->id)
            ->where('status', 'unscheduled')
            ->firstOrFail();

        $diagnostics = $item->metadata['unscheduled_diagnostics'] ?? [];

        $this->assertSame('no_candidate_rooms', $diagnostics['primary_blocker'] ?? null);
        $this->assertContains('no_candidate_rooms', $diagnostics['blockers'] ?? []);
        $this->assertSame([], $diagnostics['candidate_rooms'] ?? null);
        $this->assertStringContainsString('Add or activate a suitable room', $diagnostics['recommended_actions'][0] ?? '');
        $this->assertStringContainsString('Primary blocker: no candidate rooms', $item->explanation);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Blocked: no candidate rooms')
            ->assertSee('Add or activate a suitable room');
    }

    public function test_v041_publish_blocks_unscheduled_generation_without_override(): void
    {
        $chair = $this->seedFixture();
        $fixture = $this->createGeneratorHardLockFixture('Unscheduled Publish Block');
        $fixture['group']->update([
            'current_strength' => 9999,
            'max_capacity' => 10000,
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'Unscheduled Publish Block Run',
            'strategy' => 'room_optimized',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'Unscheduled Publish Block Run')->firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.publish', $run), [
            'effective_from' => now()->toDateString(),
            'decision_reason' => 'Attempt publish with unresolved unscheduled demand.',
        ])->assertStatus(422);

        $run->refresh();
        $this->assertNull($run->timetable_version_id);
        $this->assertNotSame('published', $run->status);
        $this->assertFalse(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->where('official_status', 'published')
            ->exists());
        $this->assertDatabaseHas('academic_pmc_timetable_publish_checks', [
            'generation_run_id' => $run->id,
            'check_type' => 'hard_conflicts',
            'status' => 'block',
        ]);
    }

    public function test_v041_parallel_sessions_in_same_slot_publish_as_distinct_official_master_sessions(): void
    {
        $chair = $this->seedFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $fixture = $this->createParallelTimetableFixture($chair, ['title' => 'Parallel Official Publish']);
        $service = app(AcademicPmcTimetableV041Service::class);

        $quality = $service->refreshConstraintsAndQuality($fixture['run']);

        $this->assertSame(0, (int) $quality->hard_conflicts);

        $version = $service->publishRun($dean, $fixture['run']->fresh(), [
            'notes' => 'Parallel official fixture',
            'effective_from' => now()->toDateString(),
            'override_reason' => 'Fixture publish should prove canonical parallel session identity.',
        ]);

        $publishedItems = AcademicPmcTimetableGenerationItem::where('generation_run_id', $fixture['run']->id)
            ->where('official_status', 'published')
            ->where('timetable_version_id', $version->id)
            ->orderBy('course_group_id')
            ->get();

        $this->assertCount(2, $publishedItems);
        $this->assertEqualsCanonicalizing(
            [$fixture['groupOne']->id, $fixture['groupTwo']->id],
            $publishedItems->pluck('course_group_id')->all()
        );
        $this->assertTrue($publishedItems->every(fn ($item) => (int) $item->program_id === (int) $fixture['program']->id));
        $this->assertSame(2, TimetableEntry::where('timetable_version_id', $version->id)->count());
        $this->assertEqualsCanonicalizing(
            $publishedItems->pluck('id')->all(),
            TimetableEntry::where('timetable_version_id', $version->id)->pluck('pmc_generation_item_id')->all()
        );

        $this->actingAs($chair)
            ->get(route('academics.pmc.official-timetable.index'))
            ->assertOk()
            ->assertSee('PMC Master Official Timetable')
            ->assertSee('Master Parallel Slot Board')
            ->assertSee('Parallel Core Section A')
            ->assertSee('Parallel Core Section B')
            ->assertSee('2 parallel');
    }

    public function test_v041_parallel_sessions_do_not_create_hard_conflicts_without_shared_resources_or_students(): void
    {
        $chair = $this->seedFixture();
        $fixture = $this->createParallelTimetableFixture($chair);

        $quality = app(AcademicPmcTimetableV041Service::class)->refreshConstraintsAndQuality($fixture['run']);

        $this->assertSame(0, (int) $quality->hard_conflicts);
        $this->assertFalse(AcademicPmcTimetableConstraint::where('generation_run_id', $fixture['run']->id)
            ->whereIn('constraint_type', ['faculty_clash', 'room_clash', 'group_clash', 'student_clash'])
            ->exists());
    }

    public function test_v041_parallel_sessions_flag_real_faculty_room_and_student_clashes(): void
    {
        $chair = $this->seedFixture();
        $service = app(AcademicPmcTimetableV041Service::class);

        foreach ([
            'faculty_clash' => ['same_teacher' => true],
            'room_clash' => ['same_room' => true],
            'student_clash' => ['shared_student' => true],
        ] as $expectedType => $overrides) {
            $fixture = $this->createParallelTimetableFixture($chair, $overrides + ['title' => 'Parallel ' . $expectedType]);

            $quality = $service->refreshConstraintsAndQuality($fixture['run']);

            $this->assertGreaterThan(0, (int) $quality->hard_conflicts, $expectedType . ' should create a hard conflict.');
            $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $fixture['run']->id)
                ->where('constraint_type', $expectedType)
                ->where('severity', 'hard')
                ->exists(), $expectedType . ' constraint should be recorded.');
        }
    }

    public function test_v041_multi_slot_sessions_flag_overlaps_beyond_the_starting_slot(): void
    {
        $chair = $this->seedFixture();
        $fixture = $this->createParallelTimetableFixture($chair, [
            'shared_student' => true,
            'first_duration' => 2,
            'second_slot_offset' => 1,
            'title' => 'Parallel Duration Overlap',
        ]);

        $quality = app(AcademicPmcTimetableV041Service::class)->refreshConstraintsAndQuality($fixture['run']);

        $this->assertGreaterThan(0, (int) $quality->hard_conflicts);
        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $fixture['run']->id)
            ->where('constraint_type', 'student_clash')
            ->where('affected_key', (string) $fixture['studentOne']->id)
            ->exists());
    }
    public function test_v041_operational_flows_create_core_records(): void
    {
        $chair = $this->seedFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $batch->id)->firstOrFail();
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        $teacher = Teacher::firstOrFail();
        $slot = TimetableSlot::firstOrFail();
        $room = Classroom::firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.course-allocation.bulk-core'), [
            'title' => 'Test Core Allocation',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_ids' => [$subject->id],
            'max_credits' => 30,
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_course_allocation_batches', ['title' => 'Test Core Allocation']);

        $this->actingAs($chair)->post(route('academics.pmc.course-groups.store'), [
            'name' => 'Test Core Section B',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
        ])->assertRedirect();
        $group = AcademicPmcCourseGroup::where('name', 'Test Core Section B')->firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.section-faculty-allocation.assign'), [
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'weekly_hours' => 3,
            'notes' => 'Test exact group allocation',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_group_faculty_assignments', ['course_group_id' => $group->id, 'teacher_id' => $teacher->id]);

        $this->actingAs($chair)->post(route('academics.pmc.locked-slots.store'), [
            'title' => 'Test Locked Lab Slot',
            'slot_type' => 'lab_block',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'is_hard_lock' => true,
            'reason' => 'Lab resource fixed.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_locked_slots', ['title' => 'Test Locked Lab Slot']);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'Test Generated Timetable',
            'strategy' => 'student_compact',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();
        $run = AcademicPmcTimetableGenerationRun::where('title', 'Test Generated Timetable')->firstOrFail();
        $this->assertTrue(AcademicPmcTimetableQualityScore::where('generation_run_id', $run->id)->exists());

        $this->actingAs($chair)->post(route('academics.pmc.timetable-change-requests.store'), [
            'change_type' => 'revision',
            'reason' => 'Resolve faculty clash.',
        ])->assertRedirect();
        $change = AcademicPmcTimetableChangeRequest::where('reason', 'Resolve faculty clash.')->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.timetable-change-requests.decide', $change), [
            'status' => 'approved',
            'decision_note' => 'Approved by PMC head.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_timetable_change_requests', ['id' => $change->id, 'status' => 'approved']);

        $this->actingAs($chair)->patch(route('academics.pmc.timetable-change-requests.decide', $change), [
            'status' => 'rejected',
            'decision_note' => 'Trying to rewrite the approved decision.',
        ])->assertStatus(422);
        $this->assertDatabaseHas('academic_pmc_timetable_change_requests', [
            'id' => $change->id,
            'status' => 'approved',
            'decision_note' => 'Approved by PMC head.',
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.substitution-intelligence.recommend'), [
            'course_group_id' => $group->id,
            'original_teacher_id' => $teacher->id,
            'substitution_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $this->assertTrue(AcademicPmcSubstitutionRecommendation::where('course_group_id', $group->id)->exists());

        $this->actingAs($chair)->post(route('academics.pmc.timetable-notifications.store'), [
            'notification_type' => 'publish',
            'recipient_type' => 'students',
            'title' => 'Test timetable published',
            'message' => 'Timetable is ready.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_timetable_notifications', ['title' => 'Test timetable published']);
    }

    public function test_timetable_change_requests_target_canonical_official_sessions(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'CHR101', 'name' => 'Change Request Systems']);
        $teacher = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $room = Classroom::factory()->create(['room_number' => 'CR-101', 'name' => 'Change Room']);
        $slot = TimetableSlot::factory()->create(['name' => 'Change Slot', 'sort_order' => 1]);
        $studentOne = Student::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id]);
        $studentTwo = Student::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Change Request Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 2,
            'status' => 'active',
        ]);
        $group->members()->create(['student_id' => $studentOne->id, 'status' => 'active']);
        $group->members()->create(['student_id' => $studentTwo->id, 'status' => 'active']);

        $version = TimetableVersion::create([
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
            'title' => 'Change Request Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $chair->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 2,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $chair->id,
        ]);
        $draftItem = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-change-requests.store'), [
            'pmc_generation_item_id' => $item->id,
            'change_type' => 'room_change',
            'reason' => 'Room is unavailable for the official section.',
        ])->assertRedirect();

        $change = AcademicPmcTimetableChangeRequest::where('pmc_generation_item_id', $item->id)->firstOrFail();

        $this->assertSame($version->id, $change->timetable_version_id);
        $this->assertSame('requested', $change->status);
        $this->assertDatabaseHas('academic_pmc_timetable_impact_records', [
            'change_request_id' => $change->id,
            'impact_type' => 'students',
            'affected_count' => 2,
        ]);
        $this->assertDatabaseHas('academic_pmc_timetable_impact_records', [
            'change_request_id' => $change->id,
            'impact_type' => 'workload',
            'affected_count' => 2,
        ]);
        $this->assertSame(
            'canonical_pmc_official_session',
            AcademicPmcTimetableImpactRecord::where('change_request_id', $change->id)->where('impact_type', 'groups')->firstOrFail()->metadata['source']
        );

        $this->actingAs($chair)->post(route('academics.pmc.timetable-change-requests.store'), [
            'pmc_generation_item_id' => $draftItem->id,
            'change_type' => 'room_change',
            'reason' => 'Draft sessions must not accept official change requests.',
        ])->assertNotFound();

        $this->assertDatabaseMissing('academic_pmc_timetable_change_requests', [
            'pmc_generation_item_id' => $draftItem->id,
        ]);
    }

    public function test_v041_seeded_data_constraints_and_access_policy_are_present(): void
    {
        $chair = $this->seedFixture();

        $this->assertTrue(AcademicPmcCourseAllocationBatch::where('title', 'PMC v0.041 Term Course Allocation')->exists());
        $this->assertTrue(AcademicPmcStudentCourseAllocation::exists());
        $this->assertTrue(AcademicPmcCourseGroup::where('group_type', 'elective_group')->exists());
        $this->assertTrue(AcademicPmcGroupFacultyAssignment::exists());
        $this->assertTrue(AcademicPmcFacultyPreference::where('faculty_type', 'adjunct')->exists());
        $this->assertTrue(AcademicPmcLockedSlot::where('is_hard_lock', true)->exists());
        $this->assertTrue(AcademicPmcTimetableConstraint::where('severity', 'hard')->exists());
        $this->assertTrue(AcademicPmcTimetableQualityScore::exists());
        $this->assertTrue(AcademicPmcTimetableNotification::exists());

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = User::factory()->create();
        $student->assignRole('student');
        $this->actingAs($student)->get(route('academics.pmc.timetable-os.index'))->assertForbidden();

        $this->actingAs($chair)->get(route('academics.pmc.timetable-os.index'))->assertOk()->assertSee('Student-course allocation');
    }
}
