<?php

namespace Tests\Feature;

use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use App\Services\AdmissionAccessPolicyService;
use App\Services\AdmissionApplicantReadinessService;
use App\Services\AdmissionAssessmentSlotService;
use App\Services\AdmissionOfferSeatSchedulerService;
use App\Services\AdmissionSafeCommunicationService;
use App\Services\AdmissionConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionOsV039Test extends TestCase
{
    use RefreshDatabase;

    public function test_v039_seeded_closure_pages_render_and_export(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicant = Applicant::whereNotNull('user_id')->firstOrFail();

        $this->actingAs($head)->get(route('admission.handoff.index'))->assertOk()->assertSee('Admission To Academics');
        $this->actingAs($head)->get(route('admission.communication-safety.index'))->assertOk()->assertSee('Blocked And Delayed Send Queue');
        $this->actingAs($head)->get(route('admission.route-access-audit.index'))->assertOk()->assertSee('v0.039 Enforcement Review');
        $this->actingAs($head)->get(route('admission.v039.exports', 'handoff'))->assertOk();
        $this->actingAs($applicant->user)->get(route('applicant.admission-operations.index'))->assertOk()->assertSee('Admission Operations');
    }

    public function test_policy_denies_unassigned_counsellor_and_allows_head(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $lead = Lead::where(function ($query) use ($counsellor) {
            $query->whereNull('assigned_to')->orWhere('assigned_to', '!=', $counsellor->id);
        })->firstOrFail();

        $policy = app(AdmissionAccessPolicyService::class);

        $this->assertFalse($policy->can($counsellor, 'lead.update', $lead));
        $this->assertTrue($policy->can($head, 'lead.update', $lead));
    }

    public function test_safe_communication_blocks_opt_out_and_automation_cannot_bypass(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $lead = Lead::firstOrFail();
        $template = AdmissionCommunicationTemplate::where('channel', 'whatsapp')->firstOrFail();

        app(AdmissionConsentService::class)->set($lead, 'whatsapp', 'opt_out', $head, 'Feature test opt-out');
        $result = app(AdmissionSafeCommunicationService::class)->queue($lead, $template, $head);

        $this->assertTrue(isset($result->blocked_by_rule));
        $this->assertDatabaseHas('admission_blocked_communications', [
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'channel' => 'whatsapp',
            'blocked_by_rule' => 'recipient_opted_out',
        ]);
    }

    public function test_applicant_self_service_is_scoped_to_own_assessment_assignment(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $applicant = Applicant::whereNotNull('user_id')->firstOrFail();
        $other = Applicant::whereNotNull('user_id')->where('id', '!=', $applicant->id)->firstOrFail();
        $otherAssignment = DB::table('admission_assessment_slot_assignments')->where('applicant_id', $other->id)->firstOrFail();

        $this->actingAs($applicant->user)->post(route('applicant.admission-operations.reschedule'), [
            'slot_assignment_id' => $otherAssignment->id,
            'reason' => 'Trying to access another applicant assignment.',
        ])->assertNotFound();
    }

    public function test_assessment_bulk_assignment_check_in_and_evaluator_replacement(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $slot = DB::table('admission_assessment_slots')->firstOrFail();
        DB::table('admission_assessment_slots')->where('id', $slot->id)->update(['capacity' => 999]);
        $applicant = Applicant::firstOrFail();
        DB::table('admission_assessment_slot_assignments')->where('slot_id', $slot->id)->where('applicant_id', $applicant->id)->delete();

        $result = app(AdmissionAssessmentSlotService::class)->bulkAssign($slot->id, collect([$applicant]), $head);
        $this->assertSame(1, $result['assigned']);

        $assignment = DB::table('admission_assessment_slot_assignments')->where('slot_id', $slot->id)->where('applicant_id', $applicant->id)->firstOrFail();
        app(AdmissionAssessmentSlotService::class)->checkIn($assignment->id, 'checked_in', $head);
        $this->assertDatabaseHas('admission_assessment_slot_assignments', ['id' => $assignment->id, 'status' => 'checked_in']);

        $invite = DB::table('admission_evaluator_invitations')->firstOrFail();
        app(AdmissionAssessmentSlotService::class)->replaceEvaluator($invite->id, $head->id, $head);
        $this->assertDatabaseHas('admission_evaluator_invitations', ['panel_id' => $invite->panel_id, 'user_id' => $head->id]);
    }

    public function test_handoff_scheduler_and_readiness_include_final_blockers(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $applicant = Applicant::firstOrFail();

        $result = app(AdmissionOfferSeatSchedulerService::class)->run();
        $this->assertArrayHasKey('expiredHolds', $result);
        $this->assertTrue(DB::table('admission_handoff_records')->exists());

        $checklist = app(AdmissionApplicantReadinessService::class)->checklist($applicant);
        foreach (['assessment', 'seat_hold', 'joining_kit', 'handoff'] as $key) {
            $this->assertArrayHasKey($key, $checklist);
        }
    }
}
