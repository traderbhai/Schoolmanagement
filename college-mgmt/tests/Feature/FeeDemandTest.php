<?php

namespace Tests\Feature;

use App\Models\FeeDemand;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeDemandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_can_view_fee_demands_list()
    {
        FeeDemand::factory()->count(5)->create();

        $response = $this->get('/academic/fee-demands');

        $response->assertStatus(200);
    }

    public function test_can_create_fee_demand()
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();

        $data = [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 100000,
            'scholarship_deduction' => 10000,
            'due_date' => now()->addMonth(),
            'status' => 'pending',
        ];

        $response = $this->post('/academic/fee-demands', $data);

        $this->assertDatabaseHas('fee_demands', ['student_id' => $student->id]);
    }

    public function test_can_view_fee_demand_details()
    {
        $feeDemand = FeeDemand::factory()->create();

        $response = $this->get("/academic/fee-demands/{$feeDemand->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_fee_demand()
    {
        $feeDemand = FeeDemand::factory()->create();

        $data = [
            'total_amount' => 150000,
            'scholarship_deduction' => 15000,
            'due_date' => now()->addMonth(),
            'status' => 'partially_paid',
        ];

        $response = $this->put("/academic/fee-demands/{$feeDemand->id}", $data);

        $this->assertEquals(150000, $feeDemand->fresh()->total_amount);
    }

    public function test_can_mark_as_paid()
    {
        $feeDemand = FeeDemand::factory()->create(['status' => 'pending']);

        $response = $this->post("/academic/fee-demands/{$feeDemand->id}/mark-paid");

        $this->assertEquals('fully_paid', $feeDemand->fresh()->status);
    }

    public function test_can_delete_fee_demand()
    {
        $feeDemand = FeeDemand::factory()->create();

        $response = $this->delete("/academic/fee-demands/{$feeDemand->id}");

        $this->assertDatabaseMissing('fee_demands', ['id' => $feeDemand->id]);
    }

    public function test_fee_demand_is_overdue()
    {
        $feeDemand = FeeDemand::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'pending',
        ]);

        $this->assertTrue($feeDemand->isOverdue());
    }

    public function test_fee_demand_not_overdue_when_paid()
    {
        $feeDemand = FeeDemand::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'fully_paid',
        ]);

        $this->assertFalse($feeDemand->isOverdue());
    }

    public function test_final_amount_calculated_correctly()
    {
        $feeDemand = FeeDemand::factory()->create([
            'total_amount' => 100000,
            'scholarship_deduction' => 20000,
            'final_amount' => 80000,
        ]);

        $this->assertEquals('80000.00', $feeDemand->fresh()->final_amount);
    }
}
