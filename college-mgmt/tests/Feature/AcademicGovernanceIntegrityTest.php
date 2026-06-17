<?php

namespace Tests\Feature;

use App\Models\CoPoMapping;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicGovernanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_accounts_user_cannot_mutate_academic_planning_curriculum_or_obe_routes(): void
    {
        $accounts = $this->userWithRole('accounts_officer');
        $term = Term::factory()->create();
        $program = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $this->actingAs($accounts)
            ->post(route('academic.academic-calendars.store'), [
                'term_id' => $term->id,
                'event_date' => now()->addWeek()->toDateString(),
                'event_name' => 'Unauthorized Calendar Change',
                'event_type' => 'semester_start',
                'is_holiday' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('academic_calendars', [
            'event_name' => 'Unauthorized Calendar Change',
        ]);

        $this->actingAs($accounts)
            ->post(route('academic.curriculum-changes.store'), [
                'program_id' => $program->id,
                'subject_id' => $subject->id,
                'title' => 'Unauthorized Curriculum Change',
                'description' => 'Accounts users must not submit academic curriculum changes.',
                'change_type' => 'modify_syllabus',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('curriculum_changes', [
            'title' => 'Unauthorized Curriculum Change',
        ]);

        $this->actingAs($accounts)
            ->post(route('academic.obe.co.store'), [
                'subject_id' => $subject->id,
                'code' => 'CO-UNAUTH',
                'description' => 'Unauthorized OBE change.',
                'bloom_level' => 'understand',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('course_outcomes', [
            'subject_id' => $subject->id,
            'code' => 'CO-UNAUTH',
        ]);
    }

    public function test_program_chair_can_submit_but_not_approve_curriculum_changes(): void
    {
        $chair = $this->userWithRole('program_chair');
        $program = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $this->actingAs($chair)
            ->post(route('academic.curriculum-changes.store'), [
                'program_id' => $program->id,
                'subject_id' => $subject->id,
                'title' => 'Program Chair Curriculum Proposal',
                'description' => 'Program chair can propose curriculum changes for Dean review.',
                'change_type' => 'modify_syllabus',
            ])
            ->assertRedirect(route('academic.curriculum-changes.index'));

        $change = CurriculumChange::where('title', 'Program Chair Curriculum Proposal')->firstOrFail();
        $this->assertSame('submitted', $change->status);
        $this->assertSame($chair->id, $change->proposed_by);

        $this->actingAs($chair)
            ->post(route('academic.curriculum-changes.approve', $change), [
                'remarks' => 'Self approval should not be allowed.',
            ])
            ->assertForbidden();

        $this->assertSame('submitted', $change->fresh()->status);
    }

    public function test_dean_can_review_only_pending_curriculum_changes(): void
    {
        $dean = $this->userWithRole('dean_academics');
        $change = CurriculumChange::create([
            'program_id' => Program::factory()->create(['is_active' => true])->id,
            'proposed_by' => User::factory()->create()->id,
            'title' => 'Dean Reviewed Curriculum Change',
            'description' => 'Pending curriculum proposal.',
            'change_type' => 'modify_syllabus',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($dean)
            ->post(route('academic.curriculum-changes.approve', $change), [
                'remarks' => 'Approved after review.',
            ])
            ->assertRedirect();

        $change->refresh();
        $this->assertSame('approved', $change->status);
        $this->assertSame($dean->id, $change->reviewed_by);

        $this->actingAs($dean)
            ->post(route('academic.curriculum-changes.reject', $change), [
                'remarks' => 'Cannot reverse final approval through direct route.',
            ])
            ->assertStatus(422);

        $this->assertSame('approved', $change->fresh()->status);
    }

    public function test_obe_outcomes_with_mapping_history_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $program = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $co = CourseOutcome::create([
            'subject_id' => $subject->id,
            'code' => 'CO1',
            'description' => 'Mapped course outcome.',
            'bloom_level' => 'understand',
            'is_active' => true,
        ]);
        $po = ProgramOutcome::create([
            'program_id' => $program->id,
            'code' => 'PO1',
            'description' => 'Mapped program outcome.',
            'category' => 'general',
            'is_active' => true,
        ]);
        CoPoMapping::create([
            'course_outcome_id' => $co->id,
            'program_outcome_id' => $po->id,
            'correlation_level' => 3,
        ]);

        $this->actingAs($admin)
            ->delete(route('academic.obe.co.destroy', $co))
            ->assertStatus(422);

        $this->assertDatabaseHas('course_outcomes', ['id' => $co->id]);

        $this->actingAs($admin)
            ->delete(route('academic.obe.po.destroy', $po))
            ->assertStatus(422);

        $this->assertDatabaseHas('program_outcomes', ['id' => $po->id]);
    }
}
