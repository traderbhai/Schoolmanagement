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
        $inactiveStudent = $this->student('Inactive Hostel Student');
        $inactiveStudent->update(['status' => 'inactive']);

        $existingAllocation = $this->allocation($existingStudent, $this->room(['room_number' => '201']));
        $newAllocation = $this->allocation($newStudent, $this->room(['room_number' => '202']));
        $this->allocation($zeroFeeStudent, $this->room(['room_number' => '203', 'monthly_fee' => 0]));
        $this->allocation($inactiveStudent, $this->room(['room_number' => '204']));

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
            ->assertSessionHas('success', 'Hostel fee demands generated: 1 created, 3 skipped.');

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
        $this->assertDatabaseMissing('hostel_fee_demands', [
            'student_id' => $inactiveStudent->id,
            'month' => '2026-06',
        ]);
    }

    public function test_admin_hostel_fee_demands_export_current_filtered_view(): void
    {
        $admin = $this->userWithRole('admin');
        $pendingStudent = $this->student('Pending Hostel Fee Export Student');
        $paidStudent = $this->student('Paid Hostel Fee Export Student');
        $pendingAllocation = $this->allocation($pendingStudent, $this->room(['room_number' => '601']));
        $paidAllocation = $this->allocation($paidStudent, $this->room(['room_number' => '602']));

        HostelFeeDemand::create([
            'hostel_allocation_id' => $pendingAllocation->id,
            'student_id' => $pendingStudent->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);
        HostelFeeDemand::create([
            'hostel_allocation_id' => $paidAllocation->id,
            'student_id' => $paidStudent->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'paid',
            'due_date' => '2026-06-30',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hostel.fees', ['status' => 'pending', 'month' => '2026-06']))
            ->assertOk()
            ->assertSee(route('admin.hostel.fees.export', ['status' => 'pending', 'month' => '2026-06']))
            ->assertSee('Showing 1 fee demand record(s)')
            ->assertSee($pendingStudent->user->name)
            ->assertDontSee($paidStudent->user->name);

        $csv = $this->actingAs($admin)
            ->get(route('admin.hostel.fees.export', ['status' => 'pending', 'month' => '2026-06']))
            ->streamedContent();
        $this->assertStringContainsString('Pending Hostel Fee Export Student', $csv);
        $this->assertStringContainsString('pending', $csv);
        $this->assertStringNotContainsString('Paid Hostel Fee Export Student', $csv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'export',
            'description' => 'Hostel fee demands exported: 1 rows; filters={"status":"pending","month":"2026-06"}',
        ]);
    }

    public function test_monthly_hostel_fee_generation_does_not_duplicate_student_after_transfer(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Transferred Fee Student');
        $oldAllocation = HostelAllocation::create([
            'hostel_room_id' => $this->room(['room_number' => '211'])->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonths(2)->toDateString(),
            'allocated_to' => now()->subDays(10)->toDateString(),
            'status' => 'transferred',
        ]);
        $this->allocation($student, $this->room(['room_number' => '212']));

        HostelFeeDemand::create([
            'hostel_allocation_id' => $oldAllocation->id,
            'student_id' => $student->id,
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
            ->assertSessionHas('success', 'Hostel fee demands generated: 0 created, 1 skipped.');

        $this->assertSame(1, HostelFeeDemand::where('student_id', $student->id)->where('month', '2026-06')->count());
    }

    public function test_monthly_hostel_fee_generation_skips_allocations_starting_after_billing_month(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Future Allocation Fee Student');
        $room = $this->room(['room_number' => '213']);

        HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => '2026-07-01',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hostel.fees.generate'), [
                'month' => '2026-06',
                'due_date' => '2026-06-30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Hostel fee demands generated: 0 created, 1 skipped.');

        $this->assertDatabaseMissing('hostel_fee_demands', [
            'student_id' => $student->id,
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
        $this->assertSame($admin->id, $demand->paid_by);

        $this->actingAs($admin)
            ->post(route('admin.hostel.fees.waive', $demand))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending hostel fee demands can be waived.');

        $this->assertSame('paid', $demand->fresh()->status);
    }

    public function test_admin_cannot_mark_inactive_student_hostel_fee_paid_from_standard_queue(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Inactive Hostel Fee Payment Student');
        $allocation = $this->allocation($student, $this->room(['room_number' => '252']));
        $student->update(['status' => 'inactive']);

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
            ->assertSessionHas('error', 'Hostel fee demands for inactive or archived students cannot be marked paid from the standard hostel fee queue. Use a documented waiver or correction workflow instead.');

        $demand->refresh();
        $this->assertSame('pending', $demand->status);
        $this->assertNull($demand->paid_at);
        $this->assertNull($demand->paid_by);
    }

    public function test_hostel_fee_waiver_requires_reason_and_stores_audit_metadata(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student('Waiver Audit Student');
        $allocation = $this->allocation($student, $this->room(['room_number' => '251']));

        $demand = HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-07',
            'amount' => 4500,
            'status' => 'pending',
            'due_date' => '2026-07-31',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.hostel.fees'))
            ->post(route('admin.hostel.fees.waive', $demand))
            ->assertRedirect(route('admin.hostel.fees'))
            ->assertSessionHasErrors('waiver_reason');

        $this->assertSame('pending', $demand->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.hostel.fees.waive', $demand), [
                'waiver_reason' => 'Dean approved waiver due documented accommodation hardship.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Hostel fee demand waived.');

        $demand->refresh();
        $this->assertSame('waived', $demand->status);
        $this->assertNull($demand->paid_at);
        $this->assertSame($admin->id, $demand->waived_by);
        $this->assertNotNull($demand->waived_at);
        $this->assertSame('Dean approved waiver due documented accommodation hardship.', $demand->waiver_reason);
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
            ->assertSee('Balance Due')
            ->assertSee('Rs. 5,200')
            ->assertSee('Academic and hostel outstanding amount')
            ->assertDontSee('Fully paid - No dues')
            ->assertSee('North Hostel')
            ->assertSee('Room 301')
            ->assertSee('2026-06')
            ->assertSee('Overdue')
            ->assertSee('Paid');
    }
}
