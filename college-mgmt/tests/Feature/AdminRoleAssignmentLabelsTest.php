<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
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
}
