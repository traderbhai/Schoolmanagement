<?php

namespace Tests\Feature;

use App\Models\FeeDemand;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsKpiDrilldownConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_accounts_overdue_demands_kpi_matches_demand_level_drilldown(): void
    {
        $user = User::where('email', 'accounts@college.com')->firstOrFail();
        $student = Student::where('status', 'active')->firstOrFail();
        $term = Term::where('program_id', $student->program_id)->first() ?: Term::factory()->create(['program_id' => $student->program_id]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 11000,
            'scholarship_deduction' => 0,
            'final_amount' => 11000,
            'penalty_amount' => 750,
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $expected = FeeDemand::where(function ($query) {
            $query->where('status', 'overdue')
                ->orWhere(fn ($query) => $query->where('status', 'pending')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->toDateString()));
        })->count();

        $this->actingAs($user)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee('Overdue Demands')
            ->assertSee(route('accounts.outstanding', ['mode' => 'overdue_demands']), false)
            ->assertSee('<div class="kpi-value">' . $expected . '</div>', false);

        $this->actingAs($user)
            ->get(route('accounts.outstanding', ['mode' => 'overdue_demands']))
            ->assertOk()
            ->assertSee('Filtered Source List (' . $expected . ')')
            ->assertSee('mode=overdue_demands');
    }

    public function test_cmc_dashboard_primary_kpis_are_summary_only_not_false_drilldowns(): void
    {
        $user = User::where('email', 'cmc@college.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee('Active Drives')
            ->assertSee('Total Placed')
            ->assertSee('Placement Rate')
            ->assertDontSee('href="#', false);
    }
}
