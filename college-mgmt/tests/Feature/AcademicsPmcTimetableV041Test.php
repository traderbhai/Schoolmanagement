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
use App\Models\TimetableSlot;
use App\Models\User;
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
