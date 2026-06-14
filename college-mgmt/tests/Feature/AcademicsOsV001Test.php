<?php

namespace Tests\Feature;

use App\Models\AcademicScopeAssignment;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\Program;
use App\Models\User;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicHierarchyService;
use App\Services\AcademicScopeService;
use App\Services\DepartmentHierarchyService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsOsV001Test extends TestCase
{
    use RefreshDatabase;

    private function roleUser(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_seeded_academics_hierarchy_has_branches_roles_members_scopes_and_audit(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        $department = Department::where('code', 'ACAD')->firstOrFail();

        $this->assertDatabaseHas('department_teams', ['department_id' => $department->id, 'type' => 'pmc', 'name' => 'PMC']);
        $this->assertDatabaseHas('department_teams', ['department_id' => $department->id, 'type' => 'coe_examination', 'name' => 'CoE / Examination']);
        $this->assertDatabaseHas('department_teams', ['department_id' => $department->id, 'type' => 'iqac', 'name' => 'IQAC']);
        $this->assertDatabaseHas('department_roles', ['department_id' => $department->id, 'code' => 'academic_department_owner']);
        $this->assertDatabaseHas('department_roles', ['department_id' => $department->id, 'code' => 'program_director']);
        $this->assertGreaterThanOrEqual(10, DepartmentMember::where('department_id', $department->id)->where('is_active', true)->count());
        $this->assertGreaterThanOrEqual(6, AcademicScopeAssignment::where('is_active', true)->count());
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => $department->id,
            'action' => 'academics_os_seeded',
        ]);
    }

    public function test_dean_can_open_governance_and_unauthorized_user_cannot(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $student = $this->roleUser('student');

        $this->actingAs($dean)
            ->get(route('academics.governance.index'))
            ->assertOk()
            ->assertSee('Academics Governance')
            ->assertSee('PMC')
            ->assertSee('CoE / Examination')
            ->assertSee('Permission Matrix');

        $this->actingAs($student)
            ->get(route('academics.governance.index'))
            ->assertForbidden();
    }

    public function test_legacy_roles_map_into_acad_hierarchy_while_exam_cell_keeps_exam_compatibility(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        $hierarchy = app(DepartmentHierarchyService::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $hod = User::where('email', 'hod@college.com')->firstOrFail();
        $exam = User::where('email', 'exam@college.com')->firstOrFail();

        $this->assertSame('pmc_head', $hierarchy->membershipFor($chair, 'ACAD')?->role?->code);
        $this->assertSame('program_leader', $hierarchy->membershipFor($hod, 'ACAD')?->role?->code);
        $this->assertSame('coe', $hierarchy->membershipFor($exam, 'ACAD')?->role?->code);
        $this->assertSame('department_owner', $hierarchy->membershipFor($exam, 'EXAM')?->role?->code);
    }

    public function test_academic_scope_service_resolves_program_scope_and_blocks_unrelated_scope(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $this->seed(AcademicsOperatingDemoSeeder::class);

        $scopes = app(AcademicScopeService::class);
        $policy = app(AcademicAccessPolicyService::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $mentor = User::where('email', 'faculty.mentor@college.com')->firstOrFail();
        $otherProgram = Program::factory()->create(['is_active' => true]);

        $this->assertTrue($scopes->canAccess($chair, 'program', $program->id));
        $this->assertTrue($policy->canManageScope($chair, 'program', $program->id));
        $this->assertFalse($scopes->canAccess($mentor, 'program', $otherProgram->id));
        $this->assertFalse($policy->canManageScope($mentor, 'program', $otherProgram->id));
    }

    public function test_department_owner_can_assign_and_deactivate_academic_scope_from_governance_routes(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $this->seed(AcademicsOperatingDemoSeeder::class);

        $owner = User::where('email', 'director@college.com')->firstOrFail();
        $member = DepartmentMember::whereHas('user', fn ($query) => $query->where('email', 'pmc.officer@college.com'))->firstOrFail();

        $this->actingAs($owner)->post(route('academics.scopes.store'), [
            'department_member_id' => $member->id,
            'scope_type' => 'program',
            'scope_id' => $program->id,
            'scope_code' => $program->code,
            'scope_name' => $program->name,
            'context' => 'pmc_support',
            'can_manage' => 1,
        ])->assertRedirect();

        $scope = AcademicScopeAssignment::where('user_id', $member->user_id)
            ->where('scope_type', 'program')
            ->where('scope_id', $program->id)
            ->firstOrFail();

        $this->assertTrue($scope->can_manage);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => Department::where('code', 'ACAD')->firstOrFail()->id,
            'actor_user_id' => $owner->id,
            'target_user_id' => $member->user_id,
            'action' => 'academic_scope_assigned',
        ]);

        $this->actingAs($owner)
            ->patch(route('academics.scopes.deactivate', $scope))
            ->assertRedirect();

        $this->assertFalse($scope->fresh()->is_active);
    }

    public function test_academic_hierarchy_service_exposes_acad_structure(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        $service = app(AcademicHierarchyService::class);
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->assertSame('ACAD', $service->department()->code);
        $this->assertTrue($service->canSeeAll($dean));
        $this->assertTrue($service->branches()->pluck('type')->contains('program_leadership'));
        $this->assertTrue($service->roles()->pluck('code')->contains('iqac_head'));
        $this->assertTrue($service->members()->pluck('user.email')->contains('exam@college.com'));
    }
}
