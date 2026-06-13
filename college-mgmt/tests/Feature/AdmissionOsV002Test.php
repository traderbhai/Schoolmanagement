<?php

namespace Tests\Feature;

use App\Models\AdmissionAssignmentEvent;
use App\Models\AdmissionAssignmentRule;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Program;
use App\Models\User;
use App\Services\AdmissionAssignmentService;
use App\Services\AdmissionAttentionService;
use App\Services\AdmissionDuplicateMergeService;
use App\Services\AdmissionKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionOsV002Test extends TestCase
{
    use RefreshDatabase;

    private function admissionUser(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function member(User $user, string $roleCode, ?DepartmentMember $manager = null): DepartmentMember
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $role = DepartmentRole::where('department_id', $department->id)->where('code', $roleCode)->firstOrFail();

        return DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $user->id,
            'reports_to_member_id' => $manager?->id,
            'is_active' => true,
        ]);
    }

    public function test_head_assigns_to_manager_and_manager_delegates_to_counsellor(): void
    {
        $head = $this->admissionUser('admission_head');
        $manager = $this->admissionUser('admission_manager');
        $counsellor = $this->admissionUser('admission_counsellor');
        $headMember = $this->member($head, 'admission_head');
        $managerMember = $this->member($manager, 'admission_manager', $headMember);
        $this->member($counsellor, 'admission_counsellor', $managerMember);
        $lead = Lead::factory()->create(['status' => 'new']);

        $service = app(AdmissionAssignmentService::class);
        $service->assignLead($lead, $manager, $head, ['reason' => 'Regional ownership', 'priority' => 'high']);
        $service->delegate($lead->fresh(), $counsellor, $manager, ['reason' => 'Counselling follow-up']);

        $lead->refresh();
        $this->assertSame($counsellor->id, $lead->assigned_to);
        $this->assertSame($counsellor->id, $lead->current_handler_user_id);
        $this->assertSame($manager->id, $lead->owner_user_id);
        $this->assertDatabaseCount('admission_assignment_events', 2);
    }

    public function test_manager_cannot_delegate_outside_subordinate_tree(): void
    {
        $head = $this->admissionUser('admission_head');
        $manager = $this->admissionUser('admission_manager');
        $outsideCounsellor = $this->admissionUser('admission_counsellor');
        $headMember = $this->member($head, 'admission_head');
        $this->member($manager, 'admission_manager', $headMember);
        $this->member($outsideCounsellor, 'admission_counsellor', $headMember);
        $lead = Lead::factory()->create(['assigned_to' => $manager->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AdmissionAssignmentService::class)->delegate($lead, $outsideCounsellor, $manager);
    }

    public function test_auto_assignment_uses_rule_then_least_workload_fallback(): void
    {
        $head = $this->admissionUser('admission_head');
        $counsellorA = $this->admissionUser('admission_counsellor');
        $counsellorB = $this->admissionUser('admission_counsellor');
        $headMember = $this->member($head, 'admission_head');
        $this->member($counsellorA, 'admission_counsellor', $headMember);
        $this->member($counsellorB, 'admission_counsellor', $headMember);
        Lead::factory()->count(3)->create(['assigned_to' => $counsellorA->id, 'status' => 'new']);
        $targetRole = DepartmentRole::where('code', 'admission_counsellor')->firstOrFail();
        AdmissionAssignmentRule::create([
            'name' => 'Web leads to counsellors',
            'object_type' => 'lead',
            'priority' => 1,
            'is_active' => true,
            'conditions' => ['source' => 'web_form'],
            'assignee_strategy' => 'least_workload',
            'target_role_id' => $targetRole->id,
        ]);

        $lead = Lead::factory()->create(['source' => 'web_form', 'status' => 'new']);
        app(AdmissionAssignmentService::class)->autoAssignLead($lead, $head);

        $this->assertSame($counsellorB->id, $lead->fresh()->assigned_to);
        $this->assertDatabaseHas('admission_assignment_events', ['subject_id' => $lead->id, 'mode' => 'auto']);
    }

    public function test_attention_and_kpi_services_are_hierarchy_scoped(): void
    {
        $head = $this->admissionUser('admission_head');
        $counsellor = $this->admissionUser('admission_counsellor');
        $headMember = $this->member($head, 'admission_head');
        $this->member($counsellor, 'admission_counsellor', $headMember);
        $lead = Lead::factory()->create([
            'assigned_to' => $counsellor->id,
            'priority' => 'urgent',
            'sla_due_at' => now()->subHour(),
            'last_activity_at' => now()->subDays(5),
            'status' => 'new',
        ]);

        $queues = app(AdmissionAttentionService::class)->queuesFor($head);
        $kpis = app(AdmissionKpiService::class)->summaryFor($head);

        $this->assertGreaterThanOrEqual(1, $queues['sla_breaches']->count());
        $this->assertGreaterThanOrEqual(1, $queues['stale_leads']->count());
        $this->assertSame(1, $kpis['sla_breaches']);
        $this->assertSame(1, $kpis['stale_leads']);
        $this->assertSame($lead->id, $queues['sla_breaches']->first()['subject_id']);
    }

    public function test_duplicate_merge_preserves_followups_and_assignment_events(): void
    {
        $head = $this->admissionUser('admission_head');
        $this->member($head, 'admission_head');
        $program = Program::factory()->create();
        $primary = Lead::factory()->create(['program_id' => $program->id, 'email' => 'same@example.com', 'phone' => null]);
        $duplicate = Lead::factory()->create(['program_id' => $program->id, 'email' => 'other@example.com', 'phone' => '999']);
        LeadFollowUp::create([
            'lead_id' => $duplicate->id,
            'assigned_to' => $head->id,
            'type' => 'call',
            'scheduled_at' => now()->addDay(),
        ]);
        AdmissionAssignmentEvent::create([
            'subject_type' => Lead::class,
            'subject_id' => $duplicate->id,
            'to_user_id' => $head->id,
            'assigned_by' => $head->id,
            'mode' => 'manual',
        ]);

        app(AdmissionDuplicateMergeService::class)->merge($primary, $duplicate, $head);

        $this->assertSame(1, $primary->fresh()->followUps()->count());
        $this->assertSame(1, AdmissionAssignmentEvent::where('subject_type', Lead::class)->where('subject_id', $primary->id)->count());
        $this->assertSame('not_interested', $duplicate->fresh()->status);
    }
}
