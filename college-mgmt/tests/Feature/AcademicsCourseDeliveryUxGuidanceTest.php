<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsCourseDeliveryUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_course_delivery_dashboard_explains_daily_faculty_sequence(): void
    {
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.index'))
            ->assertOk()
            ->assertSee('Course delivery daily sequence')
            ->assertSee('Each KPI opens the scoped source list behind the count')
            ->assertSee('1. Confirm assigned course load')
            ->assertSee('2. Review today sessions')
            ->assertSee('3. Follow up attendance risk')
            ->assertSee('4. Update engagement/material gaps')
            ->assertSee('5. Close mentor actions')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_course_delivery_source_lists_explain_signal_to_follow_up_workflow(): void
    {
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        foreach ([
            'academics.course-delivery.course-load',
            'academics.course-delivery.session-delivery',
            'academics.course-delivery.attendance-interventions',
            'academics.course-delivery.course-engagement',
            'academics.course-delivery.mentor-actions',
            'academics.course-delivery.reports',
        ] as $route) {
            $this->actingAs($faculty)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Delivery source-list workflow')
                ->assertSee('1. Filter subject/status')
                ->assertSee('2. Review delivery signal')
                ->assertSee('3. Open source workflow')
                ->assertSee('4. Update follow-up or material')
                ->assertSee('5. Export current view')
                ->assertSee('Visible filter summary')
                ->assertSee('Export current view')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }
}
