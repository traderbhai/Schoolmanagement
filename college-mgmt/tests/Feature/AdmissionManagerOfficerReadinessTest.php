<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use App\Services\AdmissionKpiDrilldownService;
use App\Services\DepartmentHierarchyService;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionManagerOfficerReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_manager_counsellor_lead_and_officer_dashboards_match_scoped_drilldowns(): void
    {
        $service = app(AdmissionKpiDrilldownService::class);

        foreach ([
            'admission.manager@college.com',
            'counsellor@college.com',
            'officer@college.com',
        ] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $dashboard = $service->dashboard($user);

            $this->actingAs($user)
                ->get(route('admission.dashboard'))
                ->assertOk()
                ->assertSee('Admission Funnel')
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Laravel', false);

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

            $this->actingAs($user)
                ->get(route('admission.payments.queue'))
                ->assertOk()
                ->assertSee('Pending Payments (' . $dashboard['kpis']['payments_pending'] . ')')
                ->assertSee('Filter: All visible pending payments');
        }
    }

    public function test_manager_counsellor_lead_and_officer_visible_workflow_links_are_reachable(): void
    {
        foreach ([
            ['admission.manager@college.com', 'admission.manager-workspace.index'],
            ['counsellor@college.com', 'admission.counsellor-desk.index'],
            ['officer@college.com', 'admission.dashboard'],
        ] as [$email, $routeName]) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->get(route($routeName));

            $response
                ->assertOk()
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Laravel', false)
                ->assertSee('<title', false);

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

    public function test_manager_has_team_scope_while_counsellor_and_officer_stay_assigned_scope(): void
    {
        $hierarchy = app(DepartmentHierarchyService::class);
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $this->assertFalse($hierarchy->canSeeAll($manager, 'ADM'));
        $this->assertTrue($hierarchy->visibleUserIds($manager, 'ADM')->contains($manager->id));
        $this->assertTrue($hierarchy->visibleUserIds($counsellor, 'ADM')->contains($counsellor->id));
        $this->assertTrue($hierarchy->visibleUserIds($officer, 'ADM')->contains($officer->id));

        $managerLead = Lead::where('assigned_to', $manager->id)->firstOrFail();
        $counsellorApplicant = Applicant::where('assigned_to', $counsellor->id)->firstOrFail();
        $officerApplicant = Applicant::where('assigned_to', $officer->id)->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admission.leads.show', $managerLead))
            ->assertOk();

        $this->actingAs($counsellor)
            ->get(route('admission.applicants.show', $counsellorApplicant))
            ->assertOk();

        $this->actingAs($counsellor)
            ->get(route('admission.applicants.show', $officerApplicant))
            ->assertForbidden();

        $this->actingAs($officer)
            ->get(route('admission.applicants.show', $officerApplicant))
            ->assertOk();

        $this->actingAs($officer)
            ->get(route('admission.applicants.show', $counsellorApplicant))
            ->assertForbidden();
    }

    public function test_manager_can_use_operational_handoff_view_but_officer_cannot_directly_open_it(): void
    {
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admission.handoff.index', ['status' => 'blocked']))
            ->assertOk()
            ->assertSee('Admission To Academics / PMC Handoff')
            ->assertSee('Filters: status=blocked');

        $this->actingAs($officer)
            ->get(route('admission.handoff.index', ['status' => 'blocked']))
            ->assertForbidden();
    }

    public function test_officer_document_and_payment_queues_explain_empty_scope(): void
    {
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        ApplicantDocument::query()->update([
            'status' => 'verified',
            'verified_by' => $officer->id,
            'verified_at' => now(),
        ]);
        AdmissionPayment::query()->update([
            'status' => 'verified',
            'verified_by' => $officer->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($officer)
            ->get(route('admission.documents.queue'))
            ->assertOk()
            ->assertSee('No pending documents in this queue')
            ->assertSee('Uploaded applicant documents appear here only when they are pending verification')
            ->assertSee('Clear Filters')
            ->assertSee('Open Applicants')
            ->assertDontSee('No pending documents in the queue')
            ->assertDontSee('N/A', false)
            ->assertDontSee(html_entity_decode('&#226;'), false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($officer)
            ->get(route('admission.payments.queue'))
            ->assertOk()
            ->assertSee('No pending payments in this queue')
            ->assertSee('Applicant payment submissions appear here only when they are pending verification')
            ->assertSee('Clear Filters')
            ->assertSee('Open Applicants')
            ->assertDontSee('No pending payments found')
            ->assertDontSee('N/A', false)
            ->assertDontSee(html_entity_decode('&#226;'), false)
            ->assertDontSee(html_entity_decode('&#8377;'), false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_applicant_list_uses_readable_missing_data_fallbacks(): void
    {
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $applicant = Applicant::factory()->create([
            'user_id' => null,
            'assigned_to' => $officer->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($officer)
            ->get(route('admission.applicants.index', ['search' => $applicant->application_number]))
            ->assertOk()
            ->assertSee('Applicant name missing')
            ->assertSee('Email not provided')
            ->assertSee('No interaction yet')
            ->assertSee('No follow-up set')
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_applicant_list_empty_state_explains_scope_and_next_step(): void
    {
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $this->actingAs($officer)
            ->get(route('admission.applicants.index', ['search' => 'no-such-applicant-record']))
            ->assertOk()
            ->assertSee('No applicants are visible in this list')
            ->assertSee('match your Admission role scope and the active filters')
            ->assertSee('Clear Filters')
            ->assertSee('Open Leads')
            ->assertDontSee('No applicants match the current filters.')
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_payment_overview_summary_and_rows_use_same_admission_scope(): void
    {
        $officer = User::where('email', 'officer@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $program = Program::factory()->create(['name' => 'Scoped Payment Program']);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'name' => 'Scoped First Installment',
            'amount' => 5000,
            'installment_number' => 1,
            'due_date' => now()->addWeek(),
            'is_active' => true,
        ]);
        $visibleApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'assigned_to' => $officer->id,
            'status' => 'selected',
            'application_number' => 'ADM-SCOPE-VISIBLE',
        ]);
        $hiddenApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'assigned_to' => $counsellor->id,
            'status' => 'selected',
            'application_number' => 'ADM-SCOPE-HIDDEN',
        ]);

        AdmissionPayment::create([
            'applicant_id' => $visibleApplicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 1000,
            'payment_date' => now(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'VISIBLE-SCOPE-PAYMENT',
            'status' => 'pending',
            'submitted_by' => $visibleApplicant->user_id,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $hiddenApplicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 9000,
            'payment_date' => now(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'HIDDEN-SCOPE-PAYMENT',
            'status' => 'pending',
            'submitted_by' => $hiddenApplicant->user_id,
        ]);

        $this->actingAs($officer)
            ->get(route('admission.payments.index', $program))
            ->assertOk()
            ->assertSee('Scoped Payment Program')
            ->assertSee('Rs. 5,000')
            ->assertSee('Rs. 1,000')
            ->assertSee('ADM-SCOPE-VISIBLE')
            ->assertDontSee('ADM-SCOPE-HIDDEN')
            ->assertDontSee('Rs. 9,000')
            ->assertDontSee('Rs. 10,000')
            ->assertDontSee('N/A', false)
            ->assertDontSee(html_entity_decode('&#226;'), false)
            ->assertDontSee(html_entity_decode('&#8377;'), false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($officer)
            ->get(route('admission.payments.index', ['program' => $program, 'status' => 'verified']))
            ->assertOk()
            ->assertSee('No payments are visible for this program')
            ->assertSee('match your Admission role scope, this program')
            ->assertSee('Clear Filters')
            ->assertSee('Open Applicants')
            ->assertSee('View Pending Queue')
            ->assertDontSee('No payments found.');
    }

    public function test_counsellor_lead_and_officer_operational_actions_are_scoped(): void
    {
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $counsellorLead = Lead::where('assigned_to', $counsellor->id)->firstOrFail();
        $officerLead = Lead::where('assigned_to', $officer->id)->first();

        if (! $officerLead) {
            $officerLead = Lead::factory()->create([
                'assigned_to' => $officer->id,
                'status' => 'new',
                'priority' => 'normal',
            ]);
        }

        $this->actingAs($counsellor)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'lead',
                'subject_id' => $officerLead->id,
                'reason' => 'callback_retry',
                'channel' => 'sms',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Unauthorized counsellor lead reminder.',
            ])
            ->assertForbidden();

        $this->actingAs($counsellor)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'lead',
                'subject_id' => $counsellorLead->id,
                'reason' => 'callback_retry',
                'channel' => 'sms',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Scoped counsellor lead reminder.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_reminder_schedules', [
            'subject_type' => Lead::class,
            'subject_id' => $counsellorLead->id,
            'assigned_to' => $counsellor->id,
            'notes' => 'Scoped counsellor lead reminder.',
        ]);

        $officerDocument = ApplicantDocument::where('status', 'pending')
            ->whereHas('applicant', fn ($query) => $query->where('assigned_to', $officer->id))
            ->firstOrFail();
        $counsellorDocument = ApplicantDocument::where('status', 'pending')
            ->whereHas('applicant', fn ($query) => $query->where('assigned_to', $counsellor->id))
            ->firstOrFail();

        $this->actingAs($officer)
            ->post(route('admission.documents.verify', $counsellorDocument))
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.documents.verify', $officerDocument))
            ->assertRedirect();

        $this->assertSame('verified', $officerDocument->fresh()->status);
        $this->assertSame('pending', $counsellorDocument->fresh()->status);

        $this->assertSame(0, DB::table('admission_reminder_schedules')
            ->where('subject_type', Lead::class)
            ->where('subject_id', $officerLead->id)
            ->where('notes', 'Unauthorized counsellor lead reminder.')
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
