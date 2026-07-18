<?php

namespace Tests\Feature;

use App\Models\CoPoMapping;
use App\Models\CoAttainment;
use App\Models\CourseOutcome;
use App\Models\CurriculumChange;
use App\Models\PoAttainment;
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

    public function test_academic_calendar_and_obe_removal_actions_explain_downstream_impact(): void
    {
        $expectations = [
            resource_path('views/academic/academic-calendars/show.blade.php') => [
                'Confirm this will not affect published timetables, holidays, exams, attendance windows, or student/faculty communication.',
            ],
            resource_path('views/academic/obe/co-index.blade.php') => [
                'Confirm it is not used in OBE mappings, attainment records, assessment rubrics, curriculum reports, or accreditation evidence.',
                'aria-label="Remove course outcome',
            ],
            resource_path('views/academic/obe/po-index.blade.php') => [
                'Confirm it is not used in OBE mappings, attainment records, curriculum reports, or accreditation evidence.',
                'Confirm it is not used in OBE mappings, specialization evidence, curriculum reports, or accreditation submissions.',
                'aria-label="Remove program outcome',
                'aria-label="Remove program specific outcome',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }

        $this->assertStringNotContainsString("confirm('Delete this event?')", file_get_contents(resource_path('views/academic/academic-calendars/show.blade.php')));
        $this->assertStringNotContainsString("confirm('Remove {{ \$co->code }}?')", file_get_contents(resource_path('views/academic/obe/co-index.blade.php')));
        $this->assertStringNotContainsString("confirm('Remove {{ \$po->code }}?')", file_get_contents(resource_path('views/academic/obe/po-index.blade.php')));
        $this->assertStringNotContainsString("confirm('Remove {{ \$pso->code }}?')", file_get_contents(resource_path('views/academic/obe/po-index.blade.php')));
    }

    public function test_scholarship_and_curriculum_decisions_explain_finance_and_delivery_impact(): void
    {
        $expectations = [
            resource_path('views/academic/scholarships/show.blade.php') => [
                'Confirm no student awards, fee-demand discounts, eligibility rules, or finance reports still depend on this scheme.',
            ],
            resource_path('views/admin/student-scholarships/index.blade.php') => [
                'Confirm the rejection reason explains eligibility, fee impact, and student communication before closing the request.',
            ],
            resource_path('views/academic/curriculum-changes/show.blade.php') => [
                'Confirm downstream timetable, course groups, OBE mapping, faculty load, and student communication impact before approval.',
                'Confirm the rejection reason is specific enough for Program Chair, Dean, and audit review.',
            ],
        ];

        foreach ($expectations as $path => $expectedSnippets) {
            $contents = file_get_contents($path);

            foreach ($expectedSnippets as $snippet) {
                $this->assertStringContainsString($snippet, $contents, $path);
            }
        }

        $this->assertStringNotContainsString("confirm('Delete this scholarship?')", file_get_contents(resource_path('views/academic/scholarships/show.blade.php')));
        $this->assertStringNotContainsString("confirm('Reject this scholarship application?')", file_get_contents(resource_path('views/admin/student-scholarships/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Approve this curriculum change?')", file_get_contents(resource_path('views/academic/curriculum-changes/show.blade.php')));
        $this->assertStringNotContainsString("confirm('Reject this curriculum change?')", file_get_contents(resource_path('views/academic/curriculum-changes/show.blade.php')));
    }

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
        $event = \App\Models\AcademicCalendar::factory()->create([
            'term_id' => $term->id,
            'event_date' => now()->addWeek()->toDateString(),
            'event_name' => 'Protected Calendar Form Event',
        ]);

        $this->actingAs($accounts)
            ->get(route('academic.academic-calendars.create'))
            ->assertForbidden();

        $this->actingAs($accounts)
            ->get(route('academic.academic-calendars.edit', $event))
            ->assertForbidden();

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

    public function test_curriculum_change_submission_requires_active_program_and_matching_active_subject(): void
    {
        $chair = $this->userWithRole('program_chair');
        $activeProgram = Program::factory()->create(['is_active' => true]);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);
        $inactiveSubject = Subject::factory()->create(['program_id' => $activeProgram->id, 'is_active' => false]);
        $otherProgramSubject = Subject::factory()->create(['program_id' => Program::factory()->create(['is_active' => true])->id, 'is_active' => true]);

        $this->actingAs($chair)
            ->post(route('academic.curriculum-changes.store'), [
                'program_id' => $inactiveProgram->id,
                'title' => 'Archived Program Proposal',
                'description' => 'Should not create governance work for inactive program.',
                'change_type' => 'modify_syllabus',
            ])
            ->assertSessionHasErrors('program_id');

        $this->actingAs($chair)
            ->post(route('academic.curriculum-changes.store'), [
                'program_id' => $activeProgram->id,
                'subject_id' => $inactiveSubject->id,
                'title' => 'Inactive Subject Proposal',
                'description' => 'Should not target inactive subject.',
                'change_type' => 'modify_syllabus',
            ])
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($chair)
            ->post(route('academic.curriculum-changes.store'), [
                'program_id' => $activeProgram->id,
                'subject_id' => $otherProgramSubject->id,
                'title' => 'Mismatched Subject Proposal',
                'description' => 'Should not target another program subject.',
                'change_type' => 'modify_syllabus',
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseMissing('curriculum_changes', ['title' => 'Archived Program Proposal']);
        $this->assertDatabaseMissing('curriculum_changes', ['title' => 'Inactive Subject Proposal']);
        $this->assertDatabaseMissing('curriculum_changes', ['title' => 'Mismatched Subject Proposal']);
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

    public function test_obe_outcomes_with_mapping_history_lock_contract_fields_but_allow_description_updates(): void
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
            ->put(route('academic.obe.co.update', $co), [
                'code' => 'CO1A',
                'description' => 'Attempt to rewrite mapped CO code.',
                'bloom_level' => 'understand',
                'is_active' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('course_outcomes', [
            'id' => $co->id,
            'code' => 'CO1',
            'description' => 'Mapped course outcome.',
        ]);

        $this->actingAs($admin)
            ->put(route('academic.obe.co.update', $co), [
                'code' => 'CO1',
                'description' => 'Clarified mapped course outcome description.',
                'bloom_level' => 'understand',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('course_outcomes', [
            'id' => $co->id,
            'code' => 'CO1',
            'description' => 'Clarified mapped course outcome description.',
        ]);

        $this->actingAs($admin)
            ->put(route('academic.obe.po.update', $po), [
                'code' => 'PO1',
                'description' => 'Attempt to rewrite mapped PO category.',
                'category' => 'management',
                'is_active' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('program_outcomes', [
            'id' => $po->id,
            'category' => 'general',
            'description' => 'Mapped program outcome.',
        ]);
    }

    public function test_obe_matrix_with_attainment_history_cannot_be_rewritten(): void
    {
        $admin = $this->userWithRole('admin');
        $program = Program::factory()->create(['is_active' => true]);
        $term = Term::factory()->create(['program_id' => $program->id]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $co = CourseOutcome::create([
            'subject_id' => $subject->id,
            'code' => 'CO1',
            'description' => 'Attained course outcome.',
            'bloom_level' => 'apply',
            'is_active' => true,
        ]);
        $po = ProgramOutcome::create([
            'program_id' => $program->id,
            'code' => 'PO1',
            'description' => 'Attained program outcome.',
            'category' => 'general',
            'is_active' => true,
        ]);
        CoPoMapping::create([
            'course_outcome_id' => $co->id,
            'program_outcome_id' => $po->id,
            'correlation_level' => 3,
        ]);
        CoAttainment::create([
            'course_outcome_id' => $co->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'direct_attainment' => 72,
            'indirect_attainment' => 68,
            'final_attainment' => 70,
            'target_attainment' => 60,
            'target_met' => true,
            'students_assessed' => 24,
            'students_attained' => 18,
        ]);
        PoAttainment::create([
            'program_outcome_id' => $po->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'attainment_value' => 70,
            'target_value' => 60,
            'target_met' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('academic.obe.matrix.save'), [
                'program_id' => $program->id,
                'subject_id' => $subject->id,
                'mappings' => [
                    $co->id => ['po_' . $po->id => 1],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('co_po_mappings', [
            'course_outcome_id' => $co->id,
            'program_outcome_id' => $po->id,
            'correlation_level' => 3,
        ]);
    }
}
