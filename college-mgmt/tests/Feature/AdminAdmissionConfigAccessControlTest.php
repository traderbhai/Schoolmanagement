<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\ScoringParameter;
use App\Models\SelectionProcessStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAdmissionConfigAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_leadership_can_open_legacy_admission_configuration(): void
    {
        $program = Program::factory()->create(['is_active' => true]);

        foreach (['admin', 'director', 'dean_academics'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.admission-config.index', $program))
                ->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_read_admission_configuration(): void
    {
        $program = Program::factory()->create(['is_active' => true]);

        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.admission-config.index', $program))->assertForbidden();
            $this->actingAs($user)->get(route('admin.admission-config.form', $program))->assertForbidden();
        }
    }

    public function test_broad_admin_group_roles_cannot_mutate_admission_configuration(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $document = RequiredDocument::create([
            'program_id' => $program->id,
            'name' => 'Identity Proof',
            'description' => 'Original document instructions',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $parameter = ScoringParameter::create([
            'selection_process_step_id' => $step->id,
            'name' => 'Communication',
            'max_score' => 50,
            'sort_order' => 1,
        ]);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'name' => 'Admission Fee',
            'amount' => 10000,
            'installment_number' => 1,
            'is_active' => true,
        ]);

        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post(route('admin.admission-config.form.update', $program), [
                    'form_sections' => json_encode(['personal' => ['enabled' => false]]),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.admission-config.documents.store', $program), [
                    'name' => "Blocked Document {$role}",
                    'is_mandatory' => true,
                    'accepted_formats' => 'pdf',
                    'max_size_kb' => 2048,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.admission-config.documents.update', $document), [
                    'name' => 'Changed Identity Proof',
                    'description' => "Changed by {$role}",
                    'is_mandatory' => false,
                    'accepted_formats' => 'jpg',
                    'max_size_kb' => 1024,
                    'sort_order' => 2,
                    'is_active' => false,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.admission-config.steps.store', $program), [
                    'name' => "Blocked Step {$role}",
                    'type' => 'gd',
                    'step_order' => 2,
                    'max_score' => 100,
                    'weightage' => 50,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.admission-config.parameters.store', $step), [
                    'name' => "Blocked Parameter {$role}",
                    'max_score' => 10,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.admission-config.fee.store', $program), [
                    'name' => "Blocked Installment {$role}",
                    'amount' => 5000,
                    'installment_number' => 2,
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('admission_form_configs', ['program_id' => $program->id]);
        $this->assertSame('Identity Proof', $document->fresh()->name);
        $this->assertSame('Original document instructions', $document->fresh()->description);
        $this->assertSame(1, SelectionProcessStep::where('program_id', $program->id)->count());
        $this->assertSame(1, ScoringParameter::where('selection_process_step_id', $step->id)->count());
        $this->assertSame(1, AdmissionFeeInstallment::where('program_id', $program->id)->count());
        $this->assertSame('Admission Fee', $installment->fresh()->name);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
