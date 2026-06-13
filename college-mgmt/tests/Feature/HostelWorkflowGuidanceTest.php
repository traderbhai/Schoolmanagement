<?php

namespace Tests\Feature;

use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelComplaint;
use App\Models\HostelRoom;
use App\Models\OutpassRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostelWorkflowGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(string $name = 'Hostel Student'): Student
    {
        $user = $this->userWithRole('student');
        $user->update(['name' => $name]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    private function room(array $overrides = []): HostelRoom
    {
        $block = HostelBlock::create([
            'name' => 'A Block',
            'gender' => 'mixed',
            'total_floors' => 2,
            'is_active' => true,
        ]);

        return HostelRoom::create(array_merge([
            'hostel_block_id' => $block->id,
            'room_number' => '101',
            'floor' => 1,
            'room_type' => 'double',
            'capacity' => 1,
            'monthly_fee' => 5000,
            'status' => 'available',
        ], $overrides));
    }

    public function test_admin_can_vacate_and_reallocate_same_bed(): void
    {
        $admin = $this->userWithRole('admin');
        $room = $this->room();
        $firstStudent = $this->student('First Student');
        $secondStudent = $this->student('Second Student');

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $firstStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);
        $room->update(['status' => 'occupied']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.vacate', $allocation))
            ->assertRedirect()
            ->assertSessionHas('success', 'Allocation vacated.');

        $allocation->refresh();
        $this->assertSame('vacated', $allocation->status);
        $this->assertNotNull($allocation->vacated_at);
        $this->assertSame('available', $room->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $secondStudent->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Student allocated successfully.');

        $allocation->refresh();
        $this->assertSame('active', $allocation->status);
        $this->assertSame($secondStudent->id, $allocation->student_id);
        $this->assertNull($allocation->vacated_at);
        $this->assertSame('occupied', $room->fresh()->status);
        $this->assertSame(1, HostelAllocation::where('hostel_room_id', $room->id)->where('bed_number', 1)->count());
    }

    public function test_admin_cannot_allocate_invalid_or_maintenance_room_bed(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $room = $this->room(['capacity' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 2,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('bed_number');

        $room->update(['status' => 'maintenance']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('hostel_room_id');
    }

    public function test_admin_can_transfer_active_allocation_to_new_room(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Transfer Student');
        $sourceRoom = $this->room(['room_number' => '101']);
        $targetRoom = $this->room(['room_number' => '102']);

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $sourceRoom->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);
        $sourceRoom->update(['status' => 'occupied']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
                'transfer_reason' => 'Student request',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Student transferred to the new room.');

        $allocation->refresh();
        $this->assertSame('transferred', $allocation->status);
        $this->assertSame('Student request', $allocation->vacate_reason);
        $this->assertSame('available', $sourceRoom->fresh()->status);
        $this->assertSame('occupied', $targetRoom->fresh()->status);

        $this->assertDatabaseHas('hostel_allocations', [
            'hostel_room_id' => $targetRoom->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_transfer_to_occupied_or_maintenance_target(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Transfer Guard Student');
        $otherStudent = $this->student('Other Hostel Student');
        $sourceRoom = $this->room(['room_number' => '201']);
        $targetRoom = $this->room(['room_number' => '202', 'capacity' => 2]);

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $sourceRoom->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);

        HostelAllocation::create([
            'hostel_room_id' => $targetRoom->id,
            'student_id' => $otherStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('bed_number');

        $targetRoom->update(['status' => 'maintenance']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 2,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('hostel_room_id');

        $this->assertSame('active', $allocation->fresh()->status);
    }

    public function test_student_cannot_create_duplicate_open_outpass(): void
    {
        $student = $this->student();
        $room = $this->room();
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        OutpassRequest::create([
            'student_id' => $student->id,
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Medical appointment',
            'out_datetime' => now()->addDay(),
            'expected_return' => now()->addDay()->addHours(4),
            'status' => 'pending',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.hostel.outpass.store'), [
                'reason' => 'Family visit',
                'out_datetime' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'expected_return' => now()->addDays(2)->addHours(3)->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(1, OutpassRequest::where('student_id', $student->id)->count());
    }

    public function test_outpass_state_transitions_are_guarded(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $room = $this->room();
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $outpass = OutpassRequest::create([
            'student_id' => $student->id,
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Project work',
            'out_datetime' => now()->addDay(),
            'expected_return' => now()->addDay()->addHours(4),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.return', $outpass))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only approved outpasses can be marked returned.');

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.approve', $outpass))
            ->assertRedirect()
            ->assertSessionHas('success', 'Outpass approved.');

        $outpass->refresh();
        $this->assertSame('approved', $outpass->status);

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.reject', $outpass), [
                'remarks' => 'Too late',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending outpass requests can be rejected.');

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.return', $outpass))
            ->assertRedirect()
            ->assertSessionHas('success', 'Student marked as returned.');

        $this->assertSame('returned', $outpass->fresh()->status);
        $this->assertNotNull($outpass->fresh()->actual_return);
    }

    public function test_student_can_submit_and_track_hostel_complaint(): void
    {
        $student = $this->student();
        $room = $this->room();
        HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.hostel.complaints.index'))
            ->assertStatus(200)
            ->assertSee('New Complaint')
            ->assertSee('Submit Complaint')
            ->assertSee('A Block / Room 101');

        $this->actingAs($student->user)
            ->post(route('student.hostel.complaints.store'), [
                'title' => 'Fan not working',
                'description' => 'The ceiling fan is not working and the room is difficult to use at night.',
                'category' => 'maintenance',
                'priority' => 'high',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Hostel complaint submitted. The warden team can now track and update it.');

        $this->assertDatabaseHas('hostel_complaints', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Fan not working',
            'status' => 'open',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.hostel.complaints.index'))
            ->assertStatus(200)
            ->assertSee('Fan not working')
            ->assertSee('Maintenance')
            ->assertSee('High');
    }

    public function test_student_without_active_allocation_cannot_submit_hostel_complaint(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.hostel.complaints.index'))
            ->assertStatus(200)
            ->assertSee('You do not have an active hostel allocation');

        $this->actingAs($student->user)
            ->post(route('student.hostel.complaints.store'), [
                'title' => 'Water issue',
                'description' => 'There is no water supply in the hostel room for the last few hours.',
                'category' => 'maintenance',
                'priority' => 'medium',
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(0, HostelComplaint::count());
    }

    public function test_admin_can_see_student_submitted_hostel_complaint(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $room = $this->room();

        HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Food quality issue',
            'description' => 'Dinner quality was poor and needs review by the hostel team.',
            'category' => 'food',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hostel.complaints'))
            ->assertStatus(200)
            ->assertSee('Food quality issue')
            ->assertSee($student->user->name)
            ->assertSee('A Block');
    }
}
