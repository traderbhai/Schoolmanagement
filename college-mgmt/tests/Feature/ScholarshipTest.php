<?php

namespace Tests\Feature;

use App\Models\Scholarship;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScholarshipTest extends TestCase
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

    public function test_can_view_scholarships_list()
    {
        Scholarship::factory()->count(5)->create();

        $response = $this->get('/academic/scholarships');

        $response->assertStatus(200);
    }

    public function test_can_create_scholarship()
    {
        $student = Student::factory()->create();

        $data = [
            'student_id' => $student->id,
            'name' => 'Merit Scholarship',
            'percentage' => 50,
            'type' => 'merit',
            'status' => 'active',
            'valid_from' => now()->startOfYear(),
            'valid_to' => now()->endOfYear(),
        ];

        $response = $this->post('/academic/scholarships', $data);

        $this->assertDatabaseHas('scholarships', ['name' => 'Merit Scholarship']);
    }

    public function test_can_view_scholarship_details()
    {
        $scholarship = Scholarship::factory()->create();

        $response = $this->get("/academic/scholarships/{$scholarship->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_scholarship()
    {
        $scholarship = Scholarship::factory()->create();

        $data = [
            'name' => 'Updated Scholarship',
            'percentage' => 75,
            'type' => 'need_based',
            'status' => 'active',
            'valid_from' => now()->startOfYear(),
            'valid_to' => now()->endOfYear(),
        ];

        $response = $this->put("/academic/scholarships/{$scholarship->id}", $data);

        $this->assertEquals('Updated Scholarship', $scholarship->fresh()->name);
    }

    public function test_can_delete_scholarship()
    {
        $scholarship = Scholarship::factory()->create();

        $response = $this->delete("/academic/scholarships/{$scholarship->id}");

        $this->assertDatabaseMissing('scholarships', ['id' => $scholarship->id]);
    }

    public function test_active_scholarship_discount()
    {
        $scholarship = Scholarship::factory()->create([
            'percentage'   => 20,
            'fixed_amount' => null,
            'status'       => 'active',
            'valid_from'   => now()->subDay(),
            'valid_to'     => now()->addDay(),
        ]);

        $discount = $scholarship->getDiscountAmount(10000);

        $this->assertEquals(2000, $discount);
    }

    public function test_inactive_scholarship_no_discount()
    {
        $scholarship = Scholarship::factory()->create([
            'percentage' => 20,
            'status' => 'inactive',
        ]);

        $discount = $scholarship->getDiscountAmount(10000);

        $this->assertEquals(0, $discount);
    }

    public function test_expired_scholarship_no_discount()
    {
        $scholarship = Scholarship::factory()->create([
            'percentage' => 20,
            'status' => 'active',
            'valid_to' => now()->subDay(),
        ]);

        $discount = $scholarship->getDiscountAmount(10000);

        $this->assertEquals(0, $discount);
    }
}
