<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionApplicantReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_applicant_navigation_exposes_owned_self_service_workflows(): void
    {
        $applicantUser = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();

        $this->actingAs($applicantUser)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Daily Work')
            ->assertSee('Track')
            ->assertSee('Settings')
            ->assertSee('Dashboard')
            ->assertSee('Checklist')
            ->assertSee('Application')
            ->assertSee('Documents')
            ->assertSee('Fees')
            ->assertSee('Admission Operations')
            ->assertSee('Offer Letters')
            ->assertSee('Status Tracker')
            ->assertSee('Notifications')
            ->assertSee(route('applicant.admission-operations.index'), false)
            ->assertSee(route('applicant.offer-letters.index'), false)
            ->assertSee(route('applicant.notifications.edit'), false)
            ->assertDontSee('href="#"', false);
    }

    public function test_applicant_visible_portal_links_are_reachable_without_debug_traces(): void
    {
        $applicantUser = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();
        $dashboard = $this->actingAs($applicantUser)->get(route('applicant.dashboard'));
        $dashboard->assertOk();

        $links = $this->applicantLinksFrom($dashboard->getContent());

        $this->assertContains(parse_url(route('applicant.admission-operations.index'), PHP_URL_PATH), $links);
        $this->assertContains(parse_url(route('applicant.offer-letters.index'), PHP_URL_PATH), $links);
        $this->assertContains(parse_url(route('applicant.notifications.edit'), PHP_URL_PATH), $links);

        foreach ($links as $link) {
            $response = $this->actingAs($applicantUser)->get($link);

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Stack trace', false)
                ->assertDontSee('Laravel\\', false)
                ->assertSee('<title', false);
        }
    }

    public function test_applicant_owned_self_service_pages_open_from_seeded_demo_data(): void
    {
        $applicantUser = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();

        foreach ([
            'applicant.dashboard',
            'applicant.checklist',
            'applicant.application.show',
            'applicant.documents.index',
            'applicant.fees.index',
            'applicant.offer-letters.index',
            'applicant.admission-operations.index',
            'applicant.status',
            'applicant.notifications.edit',
        ] as $route) {
            $this->actingAs($applicantUser)
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Stack trace', false)
                ->assertDontSee('Laravel\\', false);
        }
    }

    public function test_applicant_consent_update_is_scoped_to_their_own_applicant_record(): void
    {
        $applicantUser = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();
        $applicant = Applicant::where('user_id', $applicantUser->id)->firstOrFail();
        $otherApplicant = Applicant::whereNotNull('user_id')->where('id', '!=', $applicant->id)->firstOrFail();

        $this->actingAs($applicantUser)
            ->post(route('applicant.admission-operations.consent'), [
                'channel' => 'sms',
                'status' => 'opt_out',
                'reason' => 'Prefer email updates.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_consent_records', [
            'subject_type' => Applicant::class,
            'subject_id' => $applicant->id,
            'channel' => 'sms',
            'status' => 'opt_out',
            'source' => 'applicant_portal',
            'recorded_by' => $applicantUser->id,
        ]);

        $this->assertFalse(DB::table('admission_consent_records')
            ->where('subject_type', Applicant::class)
            ->where('subject_id', $otherApplicant->id)
            ->where('channel', 'sms')
            ->where('status', 'opt_out')
            ->where('source', 'applicant_portal')
            ->exists());
    }

    /**
     * @return array<int, string>
     */
    private function applicantLinksFrom(string $html): array
    {
        preg_match_all('/href="([^"]+)"/', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(function (string $href): ?string {
                $path = parse_url(html_entity_decode($href), PHP_URL_PATH);
                $query = parse_url(html_entity_decode($href), PHP_URL_QUERY);

                if (! is_string($path) || ! str_starts_with($path, '/applicant')) {
                    return null;
                }

                return $query ? "{$path}?{$query}" : $path;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
