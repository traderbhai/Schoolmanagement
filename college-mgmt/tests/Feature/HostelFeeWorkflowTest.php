<?php

namespace Tests\Feature;

use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostelFeeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(string $name = 'Hostel Fee Student'): Student
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
            'name' => $overrides['block_name'] ?? 'Fee Block',
            'gender' => 'mixed',
            'total_floors' => 2,
            'is_active' => true,
        ]);

        unset($overrides['block_name']);

        return HostelRoom::create(array_merge([
            'hostel_block_id' => $block->id,
            'room_number' => '201',
            'floor' => 2,
            'room_type' => 'single',
            'capacity' => 1,
            'monthly_fee' => 4500,
            'status' => 'available',
        ], $overrides));
    }

    private function allocation(Student $student, HostelRoom $room, int $bed = 1): HostelAllocation
    {
        return HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => $bed,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
    }

    public function test_admin_can_generate_monthly_hostel_fee_demands_for_active_allocations(): void
    {
        $admin = $this->userWithRole('admin');
        $existingStudent = $this->student('Existing Demand Student');
        $newStudent = $this->student('New Demand Student');
        $zeroFeeStudent = $this->student('Zero Fee Student');

        $existingAllocation = $this->allocation($existingStudent, $this->room(['room_number' => '201']));
        $newAllocation = $this->allocation($newStudent, $this->room(['room_number' => '202']));
        $this->allocation($zeroFeeStudent, $this->room(['room_number' => '203', 'monthly_fee' => 0]));

        HostelFeeDemand::create([
            'hostel_allocation_id' => $existingAllocation->id,
            'student_id' => $existingStudent->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.fees.generate'), [
                'month' => '2026-06',
                'due_date' => '2026-06-30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Hostel fee demands generated: 1 created, 2 skipped.');

        $this->assertSame(2, HostelFeeDemand::count());
        $this->assertDatabaseHas('hostel_fee_demands', [
            'hostel_allocation_id' => $newAllocation->id,
            'student_id' => $newStudent->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('hostel_fee_demands', [
            'student_id' => $zeroFeeStudent->id,
            'month' => '2026-06',
        ]);
    }

    public function test_admin_can_mark_pending_hostel_fee_paid_and_cannot_waive_paid_demand(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $allocation = $this->allocation($student, $this->room());

        $demand = HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.fees.paid', $demand))
            ->assertRedirect()
            ->assertSessionHas('success', 'Hostel fee demand marked as paid.');

        $demand->refresh();
        $this->assertSame('paid', $demand->status);
        $this->assertNotNull($demand->paid_at);

        $this->actingAs($admin)
            ->post(route('admin.hostel.fees.waive', $demand))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending hostel fee demands can be waived.');

        $this->assertSame('paid', $demand->fresh()->status);
    }

    public function test_student_fee_page_shows_hostel_demands_and_outstanding_total(): void
    {
        $student = $this->student('Visible Hostel Fee Student');
        $allocation = $this->allocation($student, $this->room([
            'block_name' => 'North Hostel',
            'room_number' => '301',
            'monthly_fee' => 5200,
        ]));

        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 5200,
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);

        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-05',
            'amount' => 5200,
            'status' => 'paid',
            'due_date' => '2026-05-31',
            'paid_at' => now(),
        ]);

        $this->actingAs($student->user)
            ->get(route('student.fees'))
            ->assertStatus(200)
            ->assertSee('Hostel Fee Demands')
            ->assertSee('Outstanding: Rs. 5,200.00')
            ->assertSee('North Hostel')
            ->assertSee('Room 301')
            ->assertSee('2026-06')
            ->assertSee('Pending')
            ->assertSee('Paid');
    }
}
