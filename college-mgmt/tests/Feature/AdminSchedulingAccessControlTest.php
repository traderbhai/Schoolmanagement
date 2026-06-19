<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSchedulingAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_global_academic_leaders_can_open_scheduling_setup_surfaces(): void
    {
        foreach (['admin', 'director', 'dean_academics'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.classrooms.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.timetable-slots.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.timetable.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.timetable.teacher-view'))->assertOk();
        }
    }

    public function test_scoped_or_non_scheduling_admin_group_roles_cannot_open_global_scheduling_setup_surfaces(): void
    {
        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.classrooms.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.timetable-slots.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.timetable.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.timetable.teacher-view'))->assertForbidden();
        }
    }

    public function test_scoped_roles_cannot_mutate_global_scheduling_records_directly(): void
    {
        $chair = $this->userWithRole('program_chair');
        $fixture = $this->timetableFixture();

        $this->actingAs($chair)->post(route('admin.classrooms.store'), [
            'name' => 'Blocked Global Room',
            'room_number' => 'BLOCKED-101',
            'capacity' => 50,
            'type' => 'lecture',
        ])->assertForbidden();

        $this->actingAs($chair)->put(route('admin.classrooms.update', $fixture['room']), [
            'name' => 'Changed By Chair',
            'room_number' => $fixture['room']->room_number,
            'capacity' => 25,
            'type' => 'lecture',
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.timetable-slots.store'), [
            'name' => 'Blocked Period',
            'start_time' => '12:00',
            'end_time' => '13:00',
            'is_break' => false,
            'sort_order' => 9,
        ])->assertForbidden();

        $this->actingAs($chair)->put(route('admin.timetable-slots.update', $fixture['slot']), [
            'name' => 'Changed Slot',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_break' => false,
            'sort_order' => 1,
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($chair)->post(route('admin.timetable.store'), [
            'semester_id' => $fixture['semester']->id,
            'course_id' => $fixture['course']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => $fixture['room']->id,
            'timetable_slot_id' => $fixture['slot']->id,
            'day_of_week' => 2,
        ])->assertForbidden();

        $this->actingAs($chair)->delete(route('admin.timetable.destroy', $fixture['entry']))
            ->assertForbidden();

        $this->assertDatabaseMissing('classrooms', ['room_number' => 'BLOCKED-101']);
        $this->assertDatabaseMissing('timetable_slots', ['name' => 'Blocked Period']);
        $this->assertSame('Original Room', $fixture['room']->fresh()->name);
        $this->assertSame('Period 1', $fixture['slot']->fresh()->name);
        $this->assertDatabaseHas('timetable_entries', ['id' => $fixture['entry']->id, 'is_active' => true]);
    }

    private function timetableFixture(): array
    {
        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $subject = Subject::factory()->create(['department_id' => $department->id]);
        $teacher = Teacher::factory()->create(['department_id' => $department->id]);
        $room = Classroom::factory()->create([
            'name' => 'Original Room',
            'room_number' => 'SCHED-101',
            'capacity' => 80,
            'type' => 'lecture',
            'is_active' => true,
        ]);
        $slot = TimetableSlot::factory()->create([
            'name' => 'Period 1',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_break' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $entry = TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'draft',
        ]);

        return compact('department', 'program', 'semester', 'course', 'subject', 'teacher', 'room', 'slot', 'entry');
    }
}
