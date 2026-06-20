<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use App\Services\AdmissionCallQueueSelectorService;
use App\Services\AdmissionKpiDrilldownService;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionCounsellorTelecallerReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_counsellor_and_telecaller_desks_open_with_scoped_kpi_drilldowns(): void
    {
        $service = app(AdmissionKpiDrilldownService::class);

        foreach ([
            ['counsellor@college.com', 'admission.counsellor-desk.index'],
            ['telecaller@college.com', 'admission.calling-desk.index'],
        ] as [$email, $deskRoute]) {
            $user = User::where('email', $email)->firstOrFail();
            $dashboard = $service->dashboard($user);

            $this->actingAs($user)
                ->get(route($deskRoute))
                ->assertOk()
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Laravel', false)
                ->assertSee('<title', false);

            $this->actingAs($user)
                ->get(route('admission.leads.index'))
                ->assertOk()
                ->assertSee($dashboard['funnelData']['leads'] . ' records after filters')
                ->assertSee('Filter: All visible leads');

            $this->actingAs($user)
                ->get(route('admission.applicants.index'))
                ->assertOk()
                ->assertSee($dashboard['funnelData']['applied'] . ' records after filters')
                ->assertSee('Filter: All visible applicants');

            $this->actingAs($user)
                ->get(route('admission.documents.queue'))
                ->assertOk()
                ->assertSee('Pending Documents (' . $dashboard['kpis']['docs_pending'] . ')')
                ->assertSee('Filter: All visible pending documents');
        }
    }

    public function test_counsellor_and_telecaller_visible_admission_links_are_reachable(): void
    {
        foreach ([
            ['counsellor@college.com', 'admission.counsellor-desk.index'],
            ['telecaller@college.com', 'admission.calling-desk.index'],
        ] as [$email, $routeName]) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->get(route($routeName));

            $response->assertOk();

            foreach ($this->internalAdmissionLinks($response->getContent()) as $path) {
                $linkResponse = $this->actingAs($user)->get($path);

                $this->assertNotContains(
                    $linkResponse->getStatusCode(),
                    [403, 404, 500],
                    "{$email} visible Admission link failed: {$path}"
                );
            }
        }
    }

    public function test_calling_queue_and_outcome_actions_are_assigned_scope_only(): void
    {
        $telecaller = User::where('email', 'telecaller@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $selector = app(AdmissionCallQueueSelectorService::class);

        $telecallerLead = Lead::where('assigned_to', $telecaller->id)->firstOrFail();
        $counsellorLead = Lead::where('assigned_to', $counsellor->id)->firstOrFail();

        $this->assertTrue($selector->canAccess($telecallerLead, $telecaller));
        $this->assertFalse($selector->canAccess($counsellorLead, $telecaller));

        $this->actingAs($telecaller)
            ->post(route('admission.calling-desk.outcome'), [
                'subject_type' => 'lead',
                'subject_id' => $counsellorLead->id,
                'disposition' => 'connected',
                'outcome' => 'interested',
                'duration_seconds' => 120,
                'next_action' => 'Unauthorized cross-scope call outcome',
                'notes' => 'Unauthorized cross-scope call outcome',
            ])
            ->assertForbidden();

        $this->actingAs($telecaller)
            ->post(route('admission.calling-desk.outcome'), [
                'subject_type' => 'lead',
                'subject_id' => $telecallerLead->id,
                'disposition' => 'not_reachable',
                'outcome' => 'callback',
                'retry_due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_seconds' => 30,
                'next_action' => 'Retry tomorrow from readiness test',
                'notes' => 'Scoped telecaller call outcome.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_call_attempts', [
            'subject_type' => Lead::class,
            'subject_id' => $telecallerLead->id,
            'caller_user_id' => $telecaller->id,
            'outcome' => 'callback',
        ]);

        $this->assertDatabaseHas('admission_reminder_schedules', [
            'subject_type' => Lead::class,
            'subject_id' => $telecallerLead->id,
            'reason' => 'callback_retry',
            'channel' => 'call',
        ]);
    }

    public function test_counsellor_reminders_and_applicant_details_are_assigned_scope_only(): void
    {
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $counsellorApplicant = Applicant::where('assigned_to', $counsellor->id)->firstOrFail();
        $officerApplicant = Applicant::where('assigned_to', $officer->id)->firstOrFail();

        $this->actingAs($counsellor)
            ->get(route('admission.applicants.show', $counsellorApplicant))
            ->assertOk();

        $this->actingAs($counsellor)
            ->get(route('admission.applicants.show', $officerApplicant))
            ->assertForbidden();

        $this->actingAs($counsellor)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'applicant',
                'subject_id' => $officerApplicant->id,
                'reason' => 'document_blocker',
                'channel' => 'email',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Unauthorized applicant reminder.',
            ])
            ->assertForbidden();

        $this->actingAs($counsellor)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'applicant',
                'subject_id' => $counsellorApplicant->id,
                'reason' => 'document_blocker',
                'channel' => 'email',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Scoped counsellor applicant reminder.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_reminder_schedules', [
            'subject_type' => Applicant::class,
            'subject_id' => $counsellorApplicant->id,
            'assigned_to' => $counsellor->id,
            'notes' => 'Scoped counsellor applicant reminder.',
        ]);
    }

    public function test_counsellor_and_telecaller_cannot_create_department_cadence_rules(): void
    {
        foreach (['counsellor@college.com', 'telecaller@college.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->actingAs($user)
                ->post(route('admission.reminders.cadence'), [
                    'name' => 'Unauthorized cadence ' . $user->id,
                    'target_type' => 'lead',
                    'reason' => 'callback_retry',
                    'channel' => 'sms',
                    'initial_delay_hours' => 24,
                    'interval_hours' => 24,
                    'max_attempts' => 3,
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, DB::table('admission_cadence_rules')
            ->where('name', 'like', 'Unauthorized cadence%')
            ->count());
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
