<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdmissionKpiDrilldownService;
use App\Services\DepartmentHierarchyService;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionHeadReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_admission_head_has_all_scope_dashboard_and_matching_primary_drilldowns(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $service = app(AdmissionKpiDrilldownService::class);

        $this->assertTrue(app(DepartmentHierarchyService::class)->canSeeAll($head, 'ADM'));

        $dashboard = $service->dashboard($head);

        $this->actingAs($head)
            ->get(route('admission.dashboard'))
            ->assertOk()
            ->assertSee('Admission Funnel')
            ->assertSee(route('admission.leads.index'), false)
            ->assertSee(route('admission.applicants.index'), false)
            ->assertSee(route('admission.documents.queue'), false)
            ->assertSee(route('admission.payments.queue'), false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('Laravel', false);

        $this->actingAs($head)
            ->get(route('admission.leads.index'))
            ->assertOk()
            ->assertSee($dashboard['funnelData']['leads'] . ' records after filters')
            ->assertSee('Filter: All visible leads');

        $this->actingAs($head)
            ->get(route('admission.applicants.index'))
            ->assertOk()
            ->assertSee($dashboard['funnelData']['applied'] . ' records after filters')
            ->assertSee('Filter: All visible applicants');

        $this->actingAs($head)
            ->get(route('admission.documents.queue'))
            ->assertOk()
            ->assertSee('Pending Documents (' . $dashboard['kpis']['docs_pending'] . ')')
            ->assertSee('Filter: All visible pending documents');

        $this->actingAs($head)
            ->get(route('admission.payments.queue'))
            ->assertOk()
            ->assertSee('Pending Payments (' . $dashboard['kpis']['payments_pending'] . ')')
            ->assertSee('Filter: All visible pending payments');
    }

    public function test_admission_head_can_open_daily_governance_and_closure_workflows(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        foreach ([
            'admission.command-center.index',
            'admission.workbench',
            'admission.attention.index',
            'admission.calling-desk.index',
            'admission.counsellor-desk.index',
            'admission.manager-workspace.index',
            'admission.communication.index',
            'admission.automations.index',
            'admission.assignment-rules.index',
            'admission.workflow-config.index',
            'admission.process-templates.index',
            'admission.assessment-control-room.index',
            'admission.assessment-slots.index',
            'admission.selection-committee.index',
            'admission.offer-rounds.index',
            'admission.handoff.index',
            'admission.reports.index',
            'admission.route-access-audit.index',
            'admission.integration-health.index',
        ] as $routeName) {
            $response = $this->actingAs($head)->get(route($routeName));

            $response
                ->assertOk()
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Laravel', false)
                ->assertSee('<title', false);
        }
    }

    public function test_admission_head_visible_admission_links_are_reachable(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $response = $this->actingAs($head)->get(route('admission.dashboard'));

        $response->assertOk();

        foreach ($this->internalAdmissionLinks($response->getContent()) as $path) {
            $linkResponse = $this->actingAs($head)->get($path);

            $this->assertNotContains(
                $linkResponse->getStatusCode(),
                [403, 404, 500],
                "Admission Head visible link failed: {$path}"
            );
            $this->assertStringNotContainsString('SERVICE ERROR', $linkResponse->getContent(), $path);
            $this->assertStringNotContainsString('Whoops', $linkResponse->getContent(), $path);
        }
    }

    public function test_admission_head_can_update_department_workflow_configuration(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->post(route('admission.workflow-config.store'), [
                'type' => 'lead_stage',
                'key' => 'head_readiness_review',
                'label' => 'Head Readiness Review',
                'sort_order' => 88,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_workflow_configs', [
            'type' => 'lead_stage',
            'key' => 'head_readiness_review',
            'label' => 'Head Readiness Review',
        ]);

        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $this->actingAs($officer)
            ->post(route('admission.workflow-config.store'), [
                'type' => 'lead_stage',
                'key' => 'unauthorized_officer_stage',
                'label' => 'Unauthorized Officer Stage',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admission_workflow_configs', [
            'key' => 'unauthorized_officer_stage',
        ]);
    }

    private function internalAdmissionLinks(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn ($href) => ! str_starts_with($href, '#'))
            ->filter(fn ($href) => ! str_starts_with($href, 'javascript:'))
            ->filter(fn ($href) => ! str_starts_with($href, 'mailto:'))
            ->filter(fn ($href) => ! preg_match('/\.(css|js|png|jpg|jpeg|svg|ico|json|webmanifest)(\?|$)/i', $href))
            ->map(function ($href) {
                if (str_starts_with($href, url('/'))) {
                    return parse_url($href, PHP_URL_PATH) . (parse_url($href, PHP_URL_QUERY) ? '?' . parse_url($href, PHP_URL_QUERY) : '');
                }

                return $href;
            })
            ->filter(fn ($href) => str_starts_with($href, '/admission'))
            ->unique()
            ->values()
            ->all();
    }
}
