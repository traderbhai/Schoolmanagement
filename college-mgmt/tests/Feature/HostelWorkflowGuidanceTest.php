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
use Illuminate\Support\Carbon;
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

    public function test_admin_can_vacate_and_reallocate_same_bed_without_overwriting_history(): void
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
        $this->assertSame('vacated', $allocation->status);
        $this->assertSame($firstStudent->id, $allocation->student_id);
        $this->assertNotNull($allocation->vacated_at);
        $this->assertSame('occupied', $room->fresh()->status);
        $this->assertSame(2, HostelAllocation::where('hostel_room_id', $room->id)->where('bed_number', 1)->count());
        $this->assertDatabaseHas('hostel_allocations', [
            'hostel_room_id' => $room->id,
            'student_id' => $secondStudent->id,
            'bed_number' => 1,
            'status' => 'active',
        ]);
    }

    public function test_admin_hostel_allocations_outpasses_and_complaints_export_current_filtered_view(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Export Hostel Student');
        $otherStudent = $this->student('Other Hostel Student');
        $room = $this->room(['room_number' => '501']);
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);
        HostelAllocation::create([
            'hostel_room_id' => $this->room(['room_number' => '502'])->id,
            'student_id' => $otherStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);
        $outpass = OutpassRequest::create([
            'student_id' => $student->id,
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Placement interview',
            'out_datetime' => now()->addDay(),
            'expected_return' => now()->addDay()->addHours(4),
            'status' => 'pending',
        ]);
        OutpassRequest::create([
            'student_id' => $student->id,
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Returned visit',
            'out_datetime' => now()->subWeek(),
            'expected_return' => now()->subWeek()->addHours(4),
            'actual_return' => now()->subWeek()->addHours(3),
            'status' => 'returned',
        ]);
        $complaint = HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Export maintenance complaint',
            'description' => 'Maintenance issue for export testing.',
            'category' => 'maintenance',
            'priority' => 'high',
            'status' => 'open',
        ]);
        HostelComplaint::create([
            'student_id' => $otherStudent->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Low priority closed complaint',
            'description' => 'Closed issue.',
            'category' => 'food',
            'priority' => 'low',
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hostel.allocations', ['search' => 'Export Hostel']))
            ->assertOk()
            ->assertSee(route('admin.hostel.allocations.export', ['search' => 'Export Hostel']))
            ->assertSee('Showing 1 active allocation record(s)')
            ->assertSee($student->user->name);

        $allocationCsv = $this->actingAs($admin)
            ->get(route('admin.hostel.allocations.export', ['search' => 'Export Hostel']))
            ->streamedContent();
        $this->assertStringContainsString('Export Hostel Student', $allocationCsv);
        $this->assertStringNotContainsString('Other Hostel Student', $allocationCsv);

        $this->actingAs($admin)
            ->get(route('admin.hostel.outpasses', ['status' => 'pending']))
            ->assertOk()
            ->assertSee(route('admin.hostel.outpasses.export', ['status' => 'pending']))
            ->assertSee('Showing 1 outpass record(s)')
            ->assertSee($outpass->reason)
            ->assertDontSee('Returned visit');

        $outpassCsv = $this->actingAs($admin)
            ->get(route('admin.hostel.outpasses.export', ['status' => 'pending']))
            ->streamedContent();
        $this->assertStringContainsString('Placement interview', $outpassCsv);
        $this->assertStringContainsString('pending', $outpassCsv);
        $this->assertStringNotContainsString('Returned visit', $outpassCsv);

        $this->actingAs($admin)
            ->get(route('admin.hostel.complaints', ['status' => 'open', 'priority' => 'high']))
            ->assertOk()
            ->assertSee(route('admin.hostel.complaints.export', ['status' => 'open', 'priority' => 'high']))
            ->assertSee('Showing 1 complaint record(s)')
            ->assertSee($complaint->title)
            ->assertDontSee('Low priority closed complaint');

        $complaintCsv = $this->actingAs($admin)
            ->get(route('admin.hostel.complaints.export', ['status' => 'open', 'priority' => 'high']))
            ->streamedContent();
        $this->assertStringContainsString('Export maintenance complaint', $complaintCsv);
        $this->assertStringContainsString('high', $complaintCsv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'export',
            'description' => 'Hostel complaints exported: 1 rows; filters={"status":"open","priority":"high"}',
        ]);
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

    public function test_admin_cannot_allocate_to_reserved_room_or_inactive_block(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $room = $this->room(['status' => 'reserved']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('hostel_room_id');

        $room->update(['status' => 'available']);
        $room->block->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('hostel_room_id');

        $this->assertSame(0, HostelAllocation::where('student_id', $student->id)->count());
    }

    public function test_admin_cannot_allocate_or_transfer_inactive_student_hostel_occupancy(): void
    {
        $admin = $this->userWithRole('admin');
        $inactiveStudent = $this->student('Inactive Hostel Allocation Student');
        $inactiveStudent->update(['status' => 'inactive']);
        $room = $this->room(['room_number' => '151']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $inactiveStudent->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, HostelAllocation::where('student_id', $inactiveStudent->id)->count());

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $inactiveStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);
        $targetRoom = $this->room(['room_number' => '152']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Inactive or archived students cannot hold active hostel allocations. Vacate the existing allocation and use student reactivation if needed.');

        $this->assertSame('active', $allocation->fresh()->status);
        $this->assertSame(1, HostelAllocation::where('student_id', $inactiveStudent->id)->count());
    }

    public function test_admin_cannot_future_date_active_hostel_allocation(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $room = $this->room(['room_number' => '161']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.store'), [
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'bed_number' => 1,
                'allocated_from' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('allocated_from');

        $this->assertDatabaseMissing('hostel_allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'status' => 'active',
        ]);
        $this->assertSame('available', $room->fresh()->status);
    }

    public function test_admin_cannot_future_date_hostel_transfer(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $sourceRoom = $this->room(['room_number' => '171']);
        $targetRoom = $this->room(['room_number' => '172']);

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $sourceRoom->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);
        $sourceRoom->update(['status' => 'occupied']);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->addWeek()->toDateString(),
                'transfer_reason' => 'Future transfer should not occupy the bed today.',
            ])
            ->assertSessionHasErrors('allocated_from');

        $this->assertSame('active', $allocation->fresh()->status);
        $this->assertSame($sourceRoom->id, $allocation->fresh()->hostel_room_id);
        $this->assertDatabaseMissing('hostel_allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $targetRoom->id,
            'status' => 'active',
        ]);
        $this->assertSame('available', $targetRoom->fresh()->status);
    }

    public function test_admin_cannot_reduce_room_capacity_below_active_occupants_or_mark_occupied_room_unusable(): void
    {
        $admin = $this->userWithRole('admin');
        $room = $this->room(['capacity' => 2, 'status' => 'occupied']);

        foreach (['First Occupant', 'Second Occupant'] as $index => $name) {
            HostelAllocation::create([
                'hostel_room_id' => $room->id,
                'student_id' => $this->student($name)->id,
                'bed_number' => $index + 1,
                'allocated_from' => now()->subWeek()->toDateString(),
                'status' => 'active',
                'allocated_by' => $admin->id,
            ]);
        }

        $payload = [
            'room_number' => $room->room_number,
            'floor' => $room->floor,
            'room_type' => $room->room_type,
            'capacity' => 1,
            'monthly_fee' => $room->monthly_fee,
            'status' => 'occupied',
        ];

        $this->actingAs($admin)
            ->put(route('admin.hostel.rooms.update', [$room->block, $room]), $payload)
            ->assertSessionHasErrors('capacity');

        $this->actingAs($admin)
            ->put(route('admin.hostel.rooms.update', [$room->block, $room]), array_merge($payload, [
                'capacity' => 2,
                'status' => 'maintenance',
            ]))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->put(route('admin.hostel.rooms.update', [$room->block, $room]), array_merge($payload, [
                'capacity' => 2,
                'status' => 'reserved',
            ]))
            ->assertSessionHasErrors('status');

        $room->refresh();
        $this->assertSame(2, $room->capacity);
        $this->assertSame('occupied', $room->status);
    }

    public function test_admin_cannot_create_or_update_rooms_under_inactive_hostel_block(): void
    {
        $admin = $this->userWithRole('admin');
        $inactiveBlock = HostelBlock::create([
            'name' => 'Closed Hostel Block',
            'gender' => 'mixed',
            'total_floors' => 3,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.rooms.store', $inactiveBlock), [
                'room_number' => '401',
                'floor' => 4,
                'room_type' => 'double',
                'capacity' => 2,
                'monthly_fee' => 6000,
            ])
            ->assertSessionHasErrors('hostel_block_id');

        $this->assertDatabaseMissing('hostel_rooms', [
            'hostel_block_id' => $inactiveBlock->id,
            'room_number' => '401',
        ]);

        $room = HostelRoom::create([
            'hostel_block_id' => $inactiveBlock->id,
            'room_number' => '402',
            'floor' => 4,
            'room_type' => 'double',
            'capacity' => 2,
            'monthly_fee' => 6000,
            'status' => 'available',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.hostel.rooms.update', [$inactiveBlock, $room]), [
                'room_number' => '402-A',
                'floor' => 4,
                'room_type' => 'single',
                'capacity' => 1,
                'monthly_fee' => 6500,
                'status' => 'maintenance',
            ])
            ->assertSessionHasErrors('hostel_block_id');

        $room->refresh();
        $this->assertSame('402', $room->room_number);
        $this->assertSame('double', $room->room_type);
        $this->assertSame(2, $room->capacity);
        $this->assertSame('available', $room->status);
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

    public function test_admin_transfer_to_previously_vacated_bed_preserves_prior_history(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Transfer History Student');
        $previousStudent = $this->student('Previous Bed Student');
        $sourceRoom = $this->room(['room_number' => '103']);
        $targetRoom = $this->room(['room_number' => '104']);

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $sourceRoom->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);

        $historicalTargetAllocation = HostelAllocation::create([
            'hostel_room_id' => $targetRoom->id,
            'student_id' => $previousStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonths(3)->toDateString(),
            'allocated_to' => now()->subMonth()->toDateString(),
            'status' => 'vacated',
            'allocated_by' => $admin->id,
            'vacated_at' => now()->subMonth(),
            'vacate_reason' => 'Previous checkout',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
                'transfer_reason' => 'Room change',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Student transferred to the new room.');

        $historicalTargetAllocation->refresh();
        $this->assertSame('vacated', $historicalTargetAllocation->status);
        $this->assertSame($previousStudent->id, $historicalTargetAllocation->student_id);
        $this->assertSame('Previous checkout', $historicalTargetAllocation->vacate_reason);
        $this->assertSame(2, HostelAllocation::where('hostel_room_id', $targetRoom->id)->where('bed_number', 1)->count());
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

    public function test_admin_cannot_transfer_to_reserved_room_or_inactive_block(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Transfer Reserved Student');
        $sourceRoom = $this->room(['room_number' => '301']);
        $targetRoom = $this->room(['room_number' => '302', 'status' => 'reserved']);

        $allocation = HostelAllocation::create([
            'hostel_room_id' => $sourceRoom->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'allocated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('hostel_room_id');

        $targetRoom->update(['status' => 'available']);
        $targetRoom->block->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.allocations.transfer', $allocation), [
                'hostel_room_id' => $targetRoom->id,
                'bed_number' => 1,
                'allocated_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('hostel_room_id');

        $this->assertSame('active', $allocation->fresh()->status);
        $this->assertSame(1, HostelAllocation::where('student_id', $student->id)->count());
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

    public function test_student_hostel_outpass_and_complaint_empty_states_explain_next_steps(): void
    {
        $student = $this->student('Hostel Empty State Student');
        $room = $this->room();
        HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.hostel.outpass'))
            ->assertOk()
            ->assertSee('No outpass requests yet')
            ->assertSee('Submit an outpass before leaving campus or hostel.')
            ->assertSee('warden team will approve, reject, or mark return status here')
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);

        $this->actingAs($student->user)
            ->get(route('student.hostel.complaints.index'))
            ->assertOk()
            ->assertSee('No hostel complaints submitted yet')
            ->assertSee('Raise a complaint for room, hygiene, food, security, or maintenance issues.')
            ->assertSee('warden team will update status and resolution notes here')
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_inactive_student_can_view_outpass_history_but_cannot_create_new_outpass(): void
    {
        $student = $this->student('Archived Outpass Student');
        $student->update(['status' => 'inactive']);
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
            'reason' => 'Historical family visit',
            'out_datetime' => now()->subWeek(),
            'expected_return' => now()->subWeek()->addHours(4),
            'status' => 'returned',
            'actual_return' => now()->subWeek()->addHours(3),
        ]);

        $this->actingAs($student->user)
            ->get(route('student.hostel.outpass'))
            ->assertOk()
            ->assertSee('New hostel outpass requests are locked')
            ->assertSee('Active students only')
            ->assertSee('Returned')
            ->assertDontSee('Submit Request');

        $this->actingAs($student->user)
            ->post(route('student.hostel.outpass.store'), [
                'reason' => 'Inactive outpass should not be accepted.',
                'out_datetime' => now()->addDay()->format('Y-m-d\TH:i'),
                'expected_return' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(1, OutpassRequest::where('student_id', $student->id)->count());
        $this->assertDatabaseMissing('outpass_requests', [
            'student_id' => $student->id,
            'reason' => 'Inactive outpass should not be accepted.',
            'status' => 'pending',
        ]);
    }

    public function test_outpass_state_transitions_are_guarded(): void
    {
        Carbon::setTestNow('2026-06-16 10:00:00');

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
            'out_datetime' => now()->addHour(),
            'expected_return' => now()->addHours(4),
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

        Carbon::setTestNow('2026-06-16 11:30:00');

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.return', $outpass))
            ->assertRedirect()
            ->assertSessionHas('success', 'Student marked as returned.');

        $this->assertSame('returned', $outpass->fresh()->status);
        $this->assertNotNull($outpass->fresh()->actual_return);

        Carbon::setTestNow();
    }

    public function test_admin_cannot_approve_expired_pending_outpass(): void
    {
        Carbon::setTestNow('2026-06-16 10:00:00');

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
            'reason' => 'Already missed appointment',
            'out_datetime' => now()->subHour(),
            'expected_return' => now()->addHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.approve', $outpass))
            ->assertRedirect()
            ->assertSessionHas('error', 'Expired outpass requests cannot be approved. Reject it and ask the student to submit a fresh request.');

        $this->assertSame('pending', $outpass->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_admin_cannot_approve_outpass_after_student_or_allocation_becomes_inactive(): void
    {
        Carbon::setTestNow('2026-06-16 10:00:00');

        $admin = $this->userWithRole('admin');

        $inactiveStudent = $this->student('Archived Hostel Outpass Student');
        $inactiveStudent->update(['status' => 'inactive']);
        $inactiveRoom = $this->room(['room_number' => 'A-201']);
        $inactiveAllocation = HostelAllocation::create([
            'hostel_room_id' => $inactiveRoom->id,
            'student_id' => $inactiveStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $inactiveStudentOutpass = OutpassRequest::create([
            'student_id' => $inactiveStudent->id,
            'hostel_allocation_id' => $inactiveAllocation->id,
            'reason' => 'Stale inactive student request',
            'out_datetime' => now()->addHour(),
            'expected_return' => now()->addHours(4),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.approve', $inactiveStudentOutpass))
            ->assertRedirect()
            ->assertSessionHas('error', 'Outpass requests for inactive or archived students cannot be approved. Reject it and ask the office to correct the student status if needed.');

        $this->assertSame('pending', $inactiveStudentOutpass->fresh()->status);
        $this->assertNull($inactiveStudentOutpass->fresh()->approved_by);

        $activeStudent = $this->student('Vacated Hostel Outpass Student');
        $vacatedRoom = $this->room(['room_number' => 'A-202']);
        $vacatedAllocation = HostelAllocation::create([
            'hostel_room_id' => $vacatedRoom->id,
            'student_id' => $activeStudent->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'vacated',
        ]);

        $vacatedAllocationOutpass = OutpassRequest::create([
            'student_id' => $activeStudent->id,
            'hostel_allocation_id' => $vacatedAllocation->id,
            'reason' => 'Stale vacated allocation request',
            'out_datetime' => now()->addHour(),
            'expected_return' => now()->addHours(4),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.approve', $vacatedAllocationOutpass))
            ->assertRedirect()
            ->assertSessionHas('error', 'Outpass requests cannot be approved after the linked hostel allocation is no longer active.');

        $this->assertSame('pending', $vacatedAllocationOutpass->fresh()->status);
        $this->assertNull($vacatedAllocationOutpass->fresh()->approved_by);

        Carbon::setTestNow();
    }

    public function test_admin_cannot_mark_outpass_returned_before_out_time(): void
    {
        Carbon::setTestNow('2026-06-16 10:00:00');

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
            'reason' => 'Evening visit',
            'out_datetime' => now()->addHours(2),
            'expected_return' => now()->addHours(5),
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.outpasses.return', $outpass))
            ->assertRedirect()
            ->assertSessionHas('error', 'Student cannot be marked returned before the approved out time.');

        $outpass->refresh();
        $this->assertSame('approved', $outpass->status);
        $this->assertNull($outpass->actual_return);

        Carbon::setTestNow();
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

    public function test_inactive_student_can_view_complaint_history_but_cannot_create_new_complaint(): void
    {
        $student = $this->student('Archived Complaint Student');
        $student->update(['status' => 'inactive']);
        $room = $this->room();
        HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);
        HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Historical hostel complaint',
            'description' => 'This complaint existed before the student profile was archived.',
            'category' => 'maintenance',
            'priority' => 'medium',
            'status' => 'closed',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.hostel.complaints.index'))
            ->assertOk()
            ->assertSee('New hostel complaints are locked')
            ->assertSee('Active students only')
            ->assertSee('Historical hostel complaint')
            ->assertDontSee('Submit Complaint');

        $this->actingAs($student->user)
            ->post(route('student.hostel.complaints.store'), [
                'title' => 'Inactive complaint',
                'description' => 'Inactive student should not create a new hostel complaint now.',
                'category' => 'maintenance',
                'priority' => 'medium',
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(1, HostelComplaint::where('student_id', $student->id)->count());
        $this->assertDatabaseMissing('hostel_complaints', [
            'student_id' => $student->id,
            'title' => 'Inactive complaint',
            'status' => 'open',
        ]);
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

    public function test_admin_cannot_resolve_or_close_hostel_complaint_without_resolution_notes(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $room = $this->room();

        $complaint = HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Water leakage',
            'description' => 'There is a water leakage near the washroom area.',
            'category' => 'maintenance',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.hostel.complaints.update', $complaint), [
                'status' => 'resolved',
            ])
            ->assertSessionHasErrors('resolution_notes');

        $this->actingAs($admin)
            ->put(route('admin.hostel.complaints.update', $complaint), [
                'status' => 'closed',
                'resolution_notes' => '   ',
            ])
            ->assertSessionHasErrors('resolution_notes');

        $complaint->refresh();
        $this->assertSame('open', $complaint->status);
        $this->assertNull($complaint->resolved_at);
    }

    public function test_admin_can_resolve_and_reopen_hostel_complaint_with_audit_context(): void
    {
        $admin = $this->userWithRole('admin');
        $warden = $this->userWithRole('hostel_warden');
        $student = $this->student();
        $room = $this->room();

        $complaint = HostelComplaint::create([
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
            ->put(route('admin.hostel.complaints.update', $complaint), [
                'status' => 'resolved',
                'assigned_to' => $warden->id,
                'resolution_notes' => 'Mess vendor was warned and replacement dinner was arranged.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Complaint updated.');

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame($warden->id, $complaint->assigned_to);
        $this->assertSame('Mess vendor was warned and replacement dinner was arranged.', $complaint->resolution_notes);
        $this->assertNotNull($complaint->resolved_at);

        $this->actingAs($admin)
            ->put(route('admin.hostel.complaints.update', $complaint), [
                'status' => 'in_progress',
                'assigned_to' => $warden->id,
                'resolution_notes' => 'Student reported repeat issue, reopened for follow-up.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Complaint updated.');

        $complaint->refresh();
        $this->assertSame('in_progress', $complaint->status);
        $this->assertNull($complaint->resolved_at);
        $this->assertSame('Student reported repeat issue, reopened for follow-up.', $complaint->resolution_notes);
    }

    public function test_admin_cannot_reopen_or_rewrite_closed_hostel_complaint_history(): void
    {
        $admin = $this->userWithRole('admin');
        $warden = $this->userWithRole('hostel_warden');
        $student = $this->student();
        $room = $this->room();

        $complaint = HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'hostel_block_id' => $room->hostel_block_id,
            'title' => 'Closed water complaint',
            'description' => 'Resolved water supply issue.',
            'category' => 'maintenance',
            'priority' => 'medium',
            'status' => 'closed',
            'assigned_to' => $warden->id,
            'resolution_notes' => 'Plumbing repair completed and accepted.',
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.hostel.complaints.update', $complaint), [
                'status' => 'in_progress',
                'assigned_to' => null,
                'resolution_notes' => 'Trying to rewrite closed complaint.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Closed hostel complaint history cannot be changed from the standard admin update route.');

        $complaint->refresh();
        $this->assertSame('closed', $complaint->status);
        $this->assertSame($warden->id, $complaint->assigned_to);
        $this->assertSame('Plumbing repair completed and accepted.', $complaint->resolution_notes);
        $this->assertNotNull($complaint->resolved_at);
    }
}
