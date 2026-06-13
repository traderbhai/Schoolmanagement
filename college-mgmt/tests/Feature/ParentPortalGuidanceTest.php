<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FeeDemand;
use App\Models\FeeStructure;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentPortalGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function parentWithStudent(): array
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('parent');
        $student = Student::factory()->create();
        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student);

        return [$user, $student];
    }

    public function test_parent_dashboard_uses_fee_demands_for_priority_and_balance(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 50000,
            'scholarship_deduction' => 0,
            'final_amount' => 50000,
            'penalty_amount' => 5000,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('parent.dashboard'))
            ->assertStatus(200)
            ->assertSee('Parent Priority')
            ->assertSee('Fee demand is overdue')
            ->assertSee('Rs. 55,000')
            ->assertSee('Open demands: 1')
            ->assertDontSee('999,999');
    }

    public function test_parent_fee_page_shows_demand_breakdown_and_overdue_guidance(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id, 'name' => 'Term 1']);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 45000,
            'scholarship_deduction' => 5000,
            'final_amount' => 40000,
            'penalty_amount' => 1000,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'overdue',
        ]);

        $this->actingAs($user)
            ->get(route('parent.children.fees', $student))
            ->assertStatus(200)
            ->assertSee('Fee follow-up needed')
            ->assertSee('Term 1')
            ->assertSee('Rs. 41,000')
            ->assertSee('Overdue')
            ->assertDontSee('999,999');
    }
}
