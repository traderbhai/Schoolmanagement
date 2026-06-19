<?php

namespace Tests\Feature;

use App\Models\FeeDemand;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScholarshipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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

    public function test_can_create_fixed_amount_scholarship_without_percentage(): void
    {
        $student = Student::factory()->create();

        $this->post('/academic/scholarships', [
            'student_id' => $student->id,
            'name' => 'Fixed Support Scholarship',
            'fixed_amount' => 7500,
            'type' => 'need_based',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ])->assertRedirect(route('academic.scholarships.index'));

        $this->assertDatabaseHas('scholarships', [
            'student_id' => $student->id,
            'name' => 'Fixed Support Scholarship',
            'percentage' => '0.00',
            'fixed_amount' => '7500.00',
        ]);
    }

    public function test_active_legacy_scholarships_require_active_student_profiles(): void
    {
        $activeStudent = Student::factory()->create(['enrollment_number' => 'ACTIVE-SCH-001', 'status' => 'active']);
        $inactiveStudent = Student::factory()->create(['enrollment_number' => 'ARCHIVED-SCH-001', 'status' => 'inactive']);

        $this->get('/academic/scholarships/create')
            ->assertOk()
            ->assertSee('ACTIVE-SCH-001')
            ->assertDontSee('ARCHIVED-SCH-001');

        $this->post('/academic/scholarships', [
            'student_id' => $inactiveStudent->id,
            'name' => 'Archived Active Scholarship',
            'percentage' => 25,
            'type' => 'merit',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('scholarships', [
            'student_id' => $inactiveStudent->id,
            'name' => 'Archived Active Scholarship',
            'status' => 'active',
        ]);

        $inactiveScholarship = Scholarship::factory()->create([
            'student_id' => $inactiveStudent->id,
            'name' => 'Inactive Historical Scholarship',
            'percentage' => 10,
            'status' => 'inactive',
            'type' => 'need_based',
        ]);

        $this->put("/academic/scholarships/{$inactiveScholarship->id}", [
            'name' => 'Inactive Historical Scholarship',
            'percentage' => 10,
            'type' => 'need_based',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame('inactive', $inactiveScholarship->fresh()->status);
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

    public function test_delete_archives_scholarship_history_instead_of_destroying_it()
    {
        $scholarship = Scholarship::factory()->create();

        $response = $this->delete("/academic/scholarships/{$scholarship->id}");

        $response->assertRedirect(route('academic.scholarships.index'));
        $this->assertDatabaseHas('scholarships', [
            'id' => $scholarship->id,
            'status' => 'inactive',
        ]);
    }

    public function test_program_chair_cannot_directly_mutate_financial_scholarships(): void
    {
        $chair = $this->userWithRole('program_chair');
        $student = Student::factory()->create();
        $scholarship = Scholarship::factory()->create(['student_id' => $student->id]);

        $this->actingAs($chair)
            ->post('/academic/scholarships', [
                'student_id' => $student->id,
                'name' => 'Unauthorized Scholarship',
                'percentage' => 25,
                'type' => 'merit',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->put("/academic/scholarships/{$scholarship->id}", [
                'name' => 'Unauthorized Update',
                'percentage' => 25,
                'type' => 'merit',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->delete("/academic/scholarships/{$scholarship->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('scholarships', ['name' => 'Unauthorized Scholarship']);
        $this->assertNotSame('Unauthorized Update', $scholarship->fresh()->name);
        $this->assertSame('active', $scholarship->fresh()->status);
    }

    public function test_program_chair_cannot_directly_view_financial_scholarship_records_or_forms(): void
    {
        $chair = $this->userWithRole('program_chair');
        $scholarship = Scholarship::factory()->create();

        $this->actingAs($chair)
            ->get('/academic/scholarships')
            ->assertForbidden();

        $this->actingAs($chair)
            ->get('/academic/scholarships/create')
            ->assertForbidden();

        $this->actingAs($chair)
            ->get("/academic/scholarships/{$scholarship->id}")
            ->assertForbidden();

        $this->actingAs($chair)
            ->get("/academic/scholarships/{$scholarship->id}/edit")
            ->assertForbidden();
    }

    public function test_scholarship_must_have_positive_discount_value(): void
    {
        $student = Student::factory()->create();

        $this->post('/academic/scholarships', [
            'student_id' => $student->id,
            'name' => 'Zero Value Scholarship',
            'percentage' => 0,
            'fixed_amount' => 0,
            'type' => 'merit',
            'status' => 'active',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('scholarships', ['name' => 'Zero Value Scholarship']);
    }

    public function test_duplicate_overlapping_active_scholarship_is_blocked(): void
    {
        $student = Student::factory()->create();
        Scholarship::factory()->create([
            'student_id' => $student->id,
            'name' => 'Merit Scholarship',
            'type' => 'merit',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ]);

        $this->post('/academic/scholarships', [
            'student_id' => $student->id,
            'name' => 'Merit Scholarship',
            'percentage' => 20,
            'type' => 'merit',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->addMonth()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(1, Scholarship::where('student_id', $student->id)->where('name', 'Merit Scholarship')->count());
    }

    public function test_applied_legacy_scholarship_financial_terms_and_archive_are_locked(): void
    {
        $student = Student::factory()->create();
        $scholarship = Scholarship::factory()->create([
            'student_id' => $student->id,
            'name' => 'Applied Legacy Scholarship',
            'percentage' => 20,
            'fixed_amount' => null,
            'type' => 'merit',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'total_amount' => 50000,
            'scholarship_deduction' => 10000,
            'final_amount' => 40000,
            'status' => 'pending',
        ]);

        $this->put("/academic/scholarships/{$scholarship->id}", [
            'name' => 'Applied Legacy Scholarship',
            'percentage' => 30,
            'fixed_amount' => null,
            'type' => 'merit',
            'status' => 'active',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => now()->endOfYear()->toDateString(),
        ])->assertStatus(422);

        $scholarship->refresh();
        $this->assertSame('20.00', $scholarship->percentage);
        $this->assertSame('active', $scholarship->status);

        $this->delete("/academic/scholarships/{$scholarship->id}")
            ->assertRedirect(route('academic.scholarships.show', $scholarship))
            ->assertSessionHas('error', 'This scholarship is linked to fee demand discount history and cannot be archived. Use an audited fee adjustment workflow instead.');

        $this->assertSame('active', $scholarship->fresh()->status);
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
