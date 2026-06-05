<?php

namespace Tests\Feature;

use App\Models\TermPromotion;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TermPromotionTest extends TestCase
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

    public function test_can_view_promotions_list()
    {
        TermPromotion::factory()->count(5)->create();

        $response = $this->get('/academic/term-promotions');

        $response->assertStatus(200);
    }

    public function test_can_view_promotion_details()
    {
        $promotion = TermPromotion::factory()->create();

        $response = $this->get("/academic/term-promotions/{$promotion->id}");

        $response->assertStatus(200);
    }

    public function test_can_approve_eligible_promotion()
    {
        $promotion = TermPromotion::factory()->create([
            'meets_academic_criteria' => true,
            'meets_attendance_criteria' => true,
            'status' => 'pending',
        ]);

        $response = $this->post("/academic/term-promotions/{$promotion->id}/approve");

        $this->assertEquals('approved', $promotion->fresh()->status);
    }

    public function test_cannot_approve_ineligible_promotion()
    {
        $promotion = TermPromotion::factory()->create([
            'meets_academic_criteria' => false,
            'meets_attendance_criteria' => true,
            'status' => 'pending',
        ]);

        $response = $this->post("/academic/term-promotions/{$promotion->id}/approve");

        $this->assertEquals('pending', $promotion->fresh()->status);
    }

    public function test_can_reject_promotion()
    {
        $promotion = TermPromotion::factory()->create(['status' => 'pending']);

        $response = $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => 'Does not meet criteria',
        ]);

        $this->assertEquals('rejected', $promotion->fresh()->status);
    }

    public function test_promotion_update_student_term()
    {
        $student = Student::factory()->create();
        $currentTerm = Term::factory()->create();
        $promotedToTerm = Term::factory()->create();

        $student->update(['current_term_id' => $currentTerm->id]);

        $promotion = TermPromotion::factory()->create([
            'student_id' => $student->id,
            'current_term_id' => $currentTerm->id,
            'promoted_to_term_id' => $promotedToTerm->id,
            'meets_academic_criteria' => true,
            'meets_attendance_criteria' => true,
        ]);

        $promotion->approve();

        $this->assertEquals($promotedToTerm->id, $student->fresh()->current_term_id);
    }

    public function test_can_reject_with_remarks()
    {
        $promotion = TermPromotion::factory()->create();
        $remarks = 'Does not meet minimum CGPA requirement';

        $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => $remarks,
        ]);

        $this->assertEquals($remarks, $promotion->fresh()->remarks);
    }
}
