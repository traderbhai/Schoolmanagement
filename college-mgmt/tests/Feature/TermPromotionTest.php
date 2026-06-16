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

    private function eligiblePendingPromotion(): TermPromotion
    {
        $student = Student::factory()->create();
        $currentTerm = Term::factory()->create();
        $promotedToTerm = Term::factory()->create();
        $student->update(['current_term_id' => $currentTerm->id]);

        return TermPromotion::factory()->create([
            'student_id' => $student->id,
            'current_term_id' => $currentTerm->id,
            'promoted_to_term_id' => $promotedToTerm->id,
            'meets_academic_criteria' => true,
            'meets_attendance_criteria' => true,
            'status' => 'pending',
        ]);
    }

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
        $promotion = $this->eligiblePendingPromotion();

        $response = $this->post("/academic/term-promotions/{$promotion->id}/approve");

        $this->assertEquals('approved', $promotion->fresh()->status);
        $this->assertEquals($promotion->promoted_to_term_id, $promotion->student->fresh()->current_term_id);
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
        $promotion = TermPromotion::factory()->create(['status' => 'pending']);
        $remarks = 'Does not meet minimum CGPA requirement';

        $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => $remarks,
        ]);

        $this->assertEquals($remarks, $promotion->fresh()->remarks);
    }

    public function test_reviewed_promotion_history_cannot_be_reapproved_rejected_or_edited()
    {
        $promotion = $this->eligiblePendingPromotion();
        $promotion->approve();

        $this->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertSessionHas('error');

        $this->post("/academic/term-promotions/{$promotion->id}/reject", [
            'remarks' => 'Changing historical decision',
        ])->assertSessionHas('error');

        $this->put("/academic/term-promotions/{$promotion->id}", [
            'status' => 'pending',
            'remarks' => 'Reopening history',
        ])->assertRedirect(route('academic.term-promotions.show', $promotion))
            ->assertSessionHas('error');

        $this->assertEquals('approved', $promotion->fresh()->status);
    }

    public function test_cannot_approve_stale_promotion_when_student_left_source_term()
    {
        $promotion = $this->eligiblePendingPromotion();
        $otherTerm = Term::factory()->create();
        $promotion->student->update(['current_term_id' => $otherTerm->id]);

        $this->post("/academic/term-promotions/{$promotion->id}/approve")
            ->assertSessionHas('error');

        $this->assertEquals('pending', $promotion->fresh()->status);
        $this->assertEquals($otherTerm->id, $promotion->student->fresh()->current_term_id);
    }

    public function test_bulk_approve_processes_only_reviewable_eligible_current_term_promotions()
    {
        $eligible = $this->eligiblePendingPromotion();
        $reviewed = $this->eligiblePendingPromotion();
        $reviewed->approve();
        $stale = $this->eligiblePendingPromotion();
        $stale->student->update(['current_term_id' => Term::factory()->create()->id]);

        $this->post(route('academic.term-promotions.bulk-approve'), [
            'promotion_ids' => [$eligible->id, $reviewed->id, $stale->id],
        ])->assertSessionHas('success');

        $this->assertEquals('approved', $eligible->fresh()->status);
        $this->assertEquals('approved', $reviewed->fresh()->status);
        $this->assertEquals('pending', $stale->fresh()->status);
        $this->assertEquals($eligible->promoted_to_term_id, $eligible->student->fresh()->current_term_id);
    }
}
