<?php

namespace Tests\Feature;

use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelComplaint;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
use App\Models\OutpassRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminHostelAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_director_can_open_hostel_operations(): void
    {
        foreach (['admin', 'director'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.hostel.index'))->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_read_hostel_operations(): void
    {
        [$block, $room, $allocation, $demand, $outpass, $complaint] = $this->hostelScenario();

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.hostel.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.hostel.blocks.edit', $block))->assertForbidden();
            $this->actingAs($user)->get(route('admin.hostel.rooms', $block))->assertForbidden();
            $this->actingAs($user)->get(route('admin.hostel.allocations'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.hostel.fees'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.hostel.outpasses'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.hostel.complaints'))->assertForbidden();
        }
    }

    public function test_broad_admin_group_roles_cannot_mutate_hostel_operations(): void
    {
        [$block, $room, $allocation, $demand, $outpass, $complaint] = $this->hostelScenario();

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post(route('admin.hostel.blocks.store'), [
                    'name' => "Blocked Block {$role}",
                    'gender' => 'mixed',
                    'total_floors' => 2,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.hostel.blocks.update', $block), [
                    'name' => "Changed {$role}",
                    'gender' => 'mixed',
                    'total_floors' => 3,
                    'is_active' => true,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.hostel.rooms.store', $block), [
                    'room_number' => "B{$user->id}",
                    'floor' => 1,
                    'room_type' => 'double',
                    'capacity' => 2,
                    'monthly_fee' => 5000,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.hostel.allocations.vacate', $allocation))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.hostel.fees.paid', $demand))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.hostel.fees.waive', $demand), [
                    'waiver_reason' => 'Blocked waiver reason with enough characters.',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.hostel.outpasses.approve', $outpass))
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.hostel.complaints.update', $complaint), [
                    'status' => 'resolved',
                    'resolution_notes' => 'Blocked resolution update.',
                ])
                ->assertForbidden();
        }

        $this->assertSame('A Block', $block->fresh()->name);
        $this->assertSame(1, HostelBlock::count());
        $this->assertSame(1, HostelRoom::count());
        $this->assertSame('active', $allocation->fresh()->status);
        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame('pending', $outpass->fresh()->status);
        $this->assertSame('open', $complaint->fresh()->status);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(): Student
    {
        $user = $this->userWithRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    private function hostelScenario(): array
    {
        $student = $this->student();
        $block = HostelBlock::create([
            'name' => 'A Block',
            'gender' => 'mixed',
            'total_floors' => 2,
            'is_active' => true,
        ]);
        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => '101',
            'floor' => 1,
            'room_type' => 'double',
            'capacity' => 2,
            'monthly_fee' => 5000,
            'status' => 'occupied',
        ]);
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        $demand = HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 5000,
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);
        $outpass = OutpassRequest::create([
            'student_id' => $student->id,
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Weekend visit',
            'out_datetime' => now()->addDay(),
            'expected_return' => now()->addDay()->addHours(4),
            'status' => 'pending',
        ]);
        $complaint = HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $block->id,
            'title' => 'Water issue',
            'description' => 'Water supply issue in the room.',
            'category' => 'maintenance',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        return [$block, $room, $allocation, $demand, $outpass, $complaint];
    }
}
