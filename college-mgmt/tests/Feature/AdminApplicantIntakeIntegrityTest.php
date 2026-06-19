<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminApplicantIntakeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_applicant_creation_requires_active_program_and_matching_open_batch(): void
    {
        $admin = $this->admin();
        $activeProgram = Program::factory()->create(['is_active' => true]);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);
        $matchingBatch = Batch::factory()->create([
            'program_id' => $activeProgram->id,
            'status' => 'active',
        ]);
        $wrongProgramBatch = Batch::factory()->create(['status' => 'active']);
        $cancelledBatch = Batch::factory()->create([
            'program_id' => $activeProgram->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.applicants.index'))
            ->post(route('admin.applicants.store'), [
                'name' => 'Inactive Program Applicant',
                'email' => 'inactive-program@example.test',
                'program_id' => $inactiveProgram->id,
                'batch_id' => null,
            ])
            ->assertRedirect(route('admin.applicants.index'))
            ->assertSessionHasErrors('program_id');

        $this->actingAs($admin)
            ->from(route('admin.applicants.index'))
            ->post(route('admin.applicants.store'), [
                'name' => 'Wrong Batch Applicant',
                'email' => 'wrong-batch@example.test',
                'program_id' => $activeProgram->id,
                'batch_id' => $wrongProgramBatch->id,
            ])
            ->assertRedirect(route('admin.applicants.index'))
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($admin)
            ->from(route('admin.applicants.index'))
            ->post(route('admin.applicants.store'), [
                'name' => 'Cancelled Batch Applicant',
                'email' => 'cancelled-batch@example.test',
                'program_id' => $activeProgram->id,
                'batch_id' => $cancelledBatch->id,
            ])
            ->assertRedirect(route('admin.applicants.index'))
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($admin)
            ->post(route('admin.applicants.store'), [
                'name' => 'Valid Intake Applicant',
                'email' => 'valid-intake@example.test',
                'phone' => '9876543210',
                'program_id' => $activeProgram->id,
                'batch_id' => $matchingBatch->id,
            ])
            ->assertRedirect(route('admin.applicants.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('applicants', [
            'program_id' => $activeProgram->id,
            'batch_id' => $matchingBatch->id,
            'status' => 'draft',
        ]);
        $this->assertSame(1, Applicant::count());
        $this->assertTrue(User::where('email', 'valid-intake@example.test')->firstOrFail()->hasRole('applicant'));
    }
}
