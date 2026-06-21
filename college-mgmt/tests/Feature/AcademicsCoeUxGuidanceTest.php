<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsCoeUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_coe_dashboard_explains_exam_operating_sequence(): void
    {
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();

        $this->actingAs($examUser)
            ->get(route('academics.coe.index'))
            ->assertOk()
            ->assertSee('CoE exam operations sequence')
            ->assertSee('Each KPI opens the scoped source list behind the count')
            ->assertSee('Owner: CoE / Examination team')
            ->assertSee('Source: Exams, marks, registrations, transcripts, appeals, anomalies')
            ->assertSee('Owner / Source')
            ->assertSee('1. Confirm exam readiness')
            ->assertSee('2. Clear marks/result blockers')
            ->assertSee('3. Release eligible hall tickets')
            ->assertSee('4. Issue transcripts from published results')
            ->assertSee('5. Resolve appeals and anomalies')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_coe_source_lists_explain_filter_review_export_and_publication_boundary(): void
    {
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();

        foreach ([
            'academics.coe.exam-readiness',
            'academics.coe.marks-results',
            'academics.coe.hall-ticket-readiness',
            'academics.coe.transcripts',
            'academics.coe.appeals-anomalies',
            'academics.coe.reports',
        ] as $route) {
            $this->actingAs($examUser)
                ->get(route($route))
                ->assertOk()
                ->assertSee('CoE source-list workflow')
                ->assertSee('Owner: CoE / Examination team')
                ->assertSee('Source:')
                ->assertSee('1. Filter exam/program/status')
                ->assertSee('2. Review blockers')
                ->assertSee('3. Open source workflow')
                ->assertSee('4. Export current view')
                ->assertSee('5. Recheck official/published boundary')
                ->assertSee('Visible filter summary')
                ->assertSee('Export current view')
                ->assertSee('Owner / Source')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }

    public function test_coe_empty_filtered_source_list_explains_official_record_boundaries(): void
    {
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();

        $this->actingAs($examUser)
            ->get(route('academics.coe.transcripts', ['search' => 'no-matching-transcript-record']))
            ->assertOk()
            ->assertSee('No CoE records match this source list')
            ->assertSee('current exam/program/status filters have no blockers')
            ->assertSee('source workflow has not yet created matching exam, result, hall-ticket, transcript, appeal, or anomaly records')
            ->assertSee('recheck published-result, eligibility, registration approval, and transcript-readiness boundaries')
            ->assertDontSee('No CoE records match the current scope and filters')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }
}
