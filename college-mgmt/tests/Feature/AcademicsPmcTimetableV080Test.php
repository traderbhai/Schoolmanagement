<?php

namespace Tests\Feature;

use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV080Test extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_input_diagnostics_render_on_dashboard_and_locked_slot_page(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Readiness Input Diagnostics')
            ->assertSee('Incomplete Pref')
            ->assertSee('Lock Collisions')
            ->assertSee('Room Blockers')
            ->assertSee('Open readiness source list');

        $this->actingAs($chair)
            ->get(route('academics.pmc.locked-slots.index'))
            ->assertOk()
            ->assertSee('Readiness Input Diagnostics')
            ->assertSee('Resolve faculty preference, locked-slot, hard-lock collision, and room/lab blockers')
            ->assertSee('Locked / Manual Slots');
    }

    public function test_hard_lock_collision_and_room_blocker_are_counted_as_launch_blockers(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $department = Department::firstOrCreate(['code' => 'V080'], ['name' => 'v080 Department']);
        $teacherUser = User::factory()->create(['name' => 'v080 Faculty']);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
            'employee_id' => 'V080-FAC',
            'designation' => 'Assistant Professor',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);
        $slot = TimetableSlot::create([
            'name' => 'v080 Period',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_break' => false,
            'sort_order' => 80,
            'is_active' => true,
        ]);
        $room = Classroom::create([
            'name' => 'v080 Room',
            'room_number' => 'V080',
            'capacity' => 20,
            'type' => 'lecture',
            'has_lab' => false,
            'is_active' => true,
        ]);

        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'faculty_type' => 'regular',
            'available_days' => [],
            'preferred_slots' => [],
            'unavailable_slots' => [],
            'max_classes_per_day' => 4,
            'max_consecutive_classes' => 3,
            'max_weekly_load' => 18,
        ]);

        foreach (['v080 hard lock A', 'v080 hard lock B'] as $title) {
            AcademicPmcLockedSlot::create([
                'title' => $title,
                'slot_type' => 'faculty_fixed',
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $slot->id,
                'is_hard_lock' => true,
                'status' => 'active',
                'created_by' => $chair->id,
                'reason' => 'v080 collision fixture',
            ]);
        }

        AcademicPmcRoomReadinessReview::create([
            'classroom_id' => $room->id,
            'scheduled_classes' => 4,
            'max_group_strength' => 35,
            'room_capacity' => 20,
            'lab_required' => true,
            'lab_ready' => false,
            'capacity_ok' => false,
            'readiness_band' => 'blocked',
            'status' => 'revision_required',
            'risk_reasons' => ['capacity_shortfall', 'lab_not_ready'],
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Lock Collisions')
            ->assertSee('Lab Not Ready')
            ->assertSee('Capacity Exceptions')
            ->assertSee('Resolve preference, locked-slot, and room/lab readiness blockers before timetable generation.');
    }
}
