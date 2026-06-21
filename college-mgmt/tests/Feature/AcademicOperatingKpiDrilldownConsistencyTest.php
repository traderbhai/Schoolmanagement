<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AcademicCoeOperatingService;
use App\Services\AcademicCourseDeliveryService;
use App\Services\AcademicIqacOperatingService;
use App\Services\AcademicProgramLeadershipService;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicOperatingKpiDrilldownConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_coe_dashboard_kpis_match_filtered_drilldowns(): void
    {
        $user = User::where('email', 'exam@college.com')->firstOrFail();
        $service = app(AcademicCoeOperatingService::class);
        $dashboard = $service->dashboard($user);

        $cases = [
            ['kpi' => 'upcoming_exams', 'section' => 'exam-readiness', 'metric' => 'upcoming_exams', 'route' => 'academics.coe.exam-readiness'],
            ['kpi' => 'marks_pending', 'section' => 'marks-results', 'metric' => 'marks_pending', 'route' => 'academics.coe.marks-results'],
            ['kpi' => 'hall_ticket_blocks', 'section' => 'hall-ticket-readiness', 'metric' => 'blocked_registrations', 'route' => 'academics.coe.hall-ticket-readiness'],
            ['kpi' => 'appeals_anomalies', 'section' => 'appeals-anomalies', 'metric' => 'appeals_anomalies', 'route' => 'academics.coe.appeals-anomalies'],
        ];

        foreach ($cases as $case) {
            $drilldown = $service->section($user, $case['section'], ['metric' => $case['metric']]);

            $this->assertSame($dashboard['kpis'][$case['kpi']], $drilldown['items']->count(), $case['kpi']);

            $this->actingAs($user)
                ->get(route($case['route'], ['metric' => $case['metric']]))
                ->assertOk()
                ->assertSee('Filtered Source List (' . $dashboard['kpis'][$case['kpi']] . ')')
                ->assertSee('Visible filter summary: Metric: ' . $case['metric']);
        }
    }

    public function test_iqac_dashboard_kpis_match_filtered_drilldowns(): void
    {
        $user = User::where('email', 'iqac.head@college.com')->firstOrFail();
        $service = app(AcademicIqacOperatingService::class);
        $dashboard = $service->dashboard($user);

        $cases = [
            ['kpi' => 'obe_gaps', 'section' => 'obe-readiness', 'metric' => 'obe_gaps', 'route' => 'academics.iqac.obe-readiness'],
            ['kpi' => 'mapping_gaps', 'section' => 'obe-readiness', 'metric' => 'mapping_gaps', 'route' => 'academics.iqac.obe-readiness'],
            ['kpi' => 'target_misses', 'section' => 'attainment-monitoring', 'metric' => 'target_misses', 'route' => 'academics.iqac.attainment-monitoring'],
            ['kpi' => 'feedback_gaps', 'section' => 'feedback-quality', 'metric' => 'feedback_gaps', 'route' => 'academics.iqac.feedback-quality'],
        ];

        foreach ($cases as $case) {
            $drilldown = $service->section($user, $case['section'], ['metric' => $case['metric']]);

            $this->assertSame($dashboard['kpis'][$case['kpi']], $drilldown['items']->count(), $case['kpi']);

            $this->actingAs($user)
                ->get(route($case['route'], ['metric' => $case['metric']]))
                ->assertOk()
                ->assertSee('Filtered Source List (' . $dashboard['kpis'][$case['kpi']] . ')')
                ->assertSee('Visible filter summary: Metric: ' . $case['metric']);
        }
    }

    public function test_program_leadership_dashboard_kpis_match_filtered_drilldowns(): void
    {
        $user = User::where('email', 'hod@college.com')->firstOrFail();
        $service = app(AcademicProgramLeadershipService::class);
        $dashboard = $service->dashboard($user);

        $cases = [
            ['kpi' => 'programs', 'section' => 'portfolio', 'metric' => 'active_programs', 'route' => 'academics.program-leadership.portfolio'],
            ['kpi' => 'active_students', 'section' => 'student-success', 'metric' => 'active_students', 'route' => 'academics.program-leadership.student-success'],
            ['kpi' => 'delivery_gaps', 'section' => 'course-delivery', 'metric' => 'delivery_gaps', 'route' => 'academics.program-leadership.course-delivery'],
            ['kpi' => 'student_risk', 'section' => 'student-success', 'metric' => 'student_risk', 'route' => 'academics.program-leadership.student-success'],
        ];

        foreach ($cases as $case) {
            $drilldown = $service->section($user, $case['section'], ['metric' => $case['metric']]);

            $this->assertSame($dashboard['kpis'][$case['kpi']], $drilldown['items']->count(), $case['kpi']);

            $this->actingAs($user)
                ->get(route($case['route'], ['metric' => $case['metric']]))
                ->assertOk()
                ->assertSee('Filtered Source List (' . $dashboard['kpis'][$case['kpi']] . ')')
                ->assertSee('Visible filter summary: Metric: ' . $case['metric']);
        }

        $this->actingAs($user)
            ->get(route('academics.program-leadership.index'))
            ->assertOk()
            ->assertSee('Active Students')
            ->assertSee(route('academics.program-leadership.student-success', ['metric' => 'active_students']), false)
            ->assertDontSee('Summary only');
    }

    public function test_course_delivery_dashboard_kpis_match_filtered_drilldowns(): void
    {
        $user = User::where('email', 'faculty.mentor@college.com')->firstOrFail();
        $service = app(AcademicCourseDeliveryService::class);
        $dashboard = $service->dashboard($user);

        $cases = [
            ['kpi' => 'assigned_courses', 'section' => 'course-load', 'metric' => 'assigned_subjects', 'route' => 'academics.course-delivery.course-load'],
            ['kpi' => 'today_sessions', 'section' => 'session-delivery', 'metric' => 'today_sessions', 'route' => 'academics.course-delivery.session-delivery'],
            ['kpi' => 'attendance_risk', 'section' => 'attendance-interventions', 'metric' => 'attendance_risk_students', 'route' => 'academics.course-delivery.attendance-interventions'],
            ['kpi' => 'mentor_actions', 'section' => 'mentor-actions', 'metric' => 'open_mentor_actions', 'route' => 'academics.course-delivery.mentor-actions'],
        ];

        foreach ($cases as $case) {
            $drilldown = $service->section($user, $case['section'], ['metric' => $case['metric']]);

            $this->assertSame($dashboard['kpis'][$case['kpi']], $drilldown['items']->count(), $case['kpi']);

            $this->actingAs($user)
                ->get(route($case['route'], ['metric' => $case['metric']]))
                ->assertOk()
                ->assertSee('Filtered Source List (' . $dashboard['kpis'][$case['kpi']] . ')')
                ->assertSee('Visible filter summary: Metric: ' . $case['metric']);
        }
    }
}
