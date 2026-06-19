<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRoleAssignmentLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_assignment_form_uses_batch_code_instead_of_raw_id_fallback(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');

        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management']);
        $program = Program::factory()->create([
            'department_id' => $department->id,
            'name' => 'Post Graduate Diploma in Management',
            'code' => 'PGDM',
            'is_active' => true,
        ]);
        $batch = Batch::factory()->create([
            'program_id' => $program->id,
            'name' => 'PGDM 2026',
            'code' => 'PGDM-26',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.role-assignments.create'))
            ->assertOk()
            ->assertSee('PGDM-26')
            ->assertSee('PGDM')
            ->assertDontSee('Batch #'.$batch->id);
    }

    public function test_scoped_role_assignment_revocation_preserves_history_and_actual_access_state(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        $target = User::factory()->create(['name' => 'Program Chair User']);
        $target->assignRole('program_chair');
        $program = Program::factory()->create(['is_active' => true]);

        $assignment = RoleProgramAssignment::create([
            'user_id' => $target->id,
            'role_name' => 'program_chair',
            'program_id' => $program->id,
            'batch_id' => null,
            'is_active' => true,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.role-assignments.destroy', $assignment))
            ->assertRedirect()
            ->assertSessionHas('success', 'Role assignment removed.');

        $assignment->refresh();
        $this->assertFalse($assignment->is_active);
        $this->assertSame($admin->id, $assignment->revoked_by);
        $this->assertNotNull($assignment->revoked_at);
        $this->assertFalse($target->fresh()->hasRole('program_chair'));
        $this->assertDatabaseHas('role_program_assignments', [
            'id' => $assignment->id,
            'is_active' => false,
        ]);
        $this->assertTrue(AuditLog::where('action', 'scoped_role_revoked')->where('target_id', $assignment->id)->exists());
    }

    public function test_scoped_role_assignment_store_reactivates_previous_assignment_history(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        $target = User::factory()->create(['name' => 'Program Chair User']);
        $program = Program::factory()->create(['is_active' => true]);

        $assignment = RoleProgramAssignment::create([
            'user_id' => $target->id,
            'role_name' => 'program_chair',
            'program_id' => $program->id,
            'batch_id' => null,
            'is_active' => false,
            'assigned_by' => $admin->id,
            'assigned_at' => now()->subMonth(),
            'revoked_by' => $admin->id,
            'revoked_at' => now()->subWeek(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.role-assignments.store'), [
                'user_id' => $target->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
            ])
            ->assertRedirect(route('admin.role-assignments.index'))
            ->assertSessionHas('success', 'Role assignment created successfully.');

        $assignment->refresh();
        $this->assertTrue($assignment->is_active);
        $this->assertNull($assignment->revoked_by);
        $this->assertNull($assignment->revoked_at);
        $this->assertSame(1, RoleProgramAssignment::where('user_id', $target->id)->where('role_name', 'program_chair')->where('program_id', $program->id)->count());
        $this->assertTrue($target->fresh()->hasRole('program_chair'));
    }

    public function test_scoped_role_assignment_requires_batch_to_belong_to_selected_program(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        $target = User::factory()->create(['name' => 'Program Chair User']);

        $program = Program::factory()->create(['is_active' => true]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $foreignBatch = Batch::factory()->create([
            'program_id' => $otherProgram->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.role-assignments.create'))
            ->post(route('admin.role-assignments.store'), [
                'user_id' => $target->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'batch_id' => $foreignBatch->id,
            ])
            ->assertRedirect(route('admin.role-assignments.create'))
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('role_program_assignments', [
            'user_id' => $target->id,
            'role_name' => 'program_chair',
            'program_id' => $program->id,
            'batch_id' => $foreignBatch->id,
            'is_active' => true,
        ]);
        $this->assertFalse($target->fresh()->hasRole('program_chair'));
    }

    public function test_scoped_role_assignment_accepts_matching_active_program_and_batch(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        $target = User::factory()->create(['name' => 'Program Chair User']);

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create([
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.role-assignments.store'), [
                'user_id' => $target->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect(route('admin.role-assignments.index'))
            ->assertSessionHas('success', 'Role assignment created successfully.');

        $this->assertDatabaseHas('role_program_assignments', [
            'user_id' => $target->id,
            'role_name' => 'program_chair',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'is_active' => true,
        ]);
        $this->assertTrue($target->fresh()->hasRole('program_chair'));
    }

    public function test_non_security_admin_cannot_manage_scoped_role_assignments_by_direct_route(): void
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $programChair = User::factory()->create(['name' => 'Program Chair']);
        $programChair->assignRole('program_chair');
        $target = User::factory()->create(['name' => 'Target User']);
        $program = Program::factory()->create(['is_active' => true]);
        $assignment = RoleProgramAssignment::create([
            'user_id' => $target->id,
            'role_name' => 'program_chair',
            'program_id' => $program->id,
            'batch_id' => null,
            'is_active' => true,
            'assigned_by' => $programChair->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($programChair)
            ->get(route('admin.role-assignments.index'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.role-assignments.create'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.role-assignments.store'), [
                'user_id' => $target->id,
                'role_name' => 'exam_cell',
                'program_id' => $program->id,
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->delete(route('admin.role-assignments.destroy', $assignment))
            ->assertForbidden();

        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertFalse($target->fresh()->hasRole('exam_cell'));
    }
}
