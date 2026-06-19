<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantScore;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\Notification;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\ScoringParameter;
use App\Models\SelectionProcessStep;
use App\Models\SelectionSession;
use App\Models\SessionApplicant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionConfigurationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_required_document_with_applicant_upload_history_cannot_be_deleted_or_recontracted(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create(['status' => 'submitted']);
        $document = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Identity Proof',
            'description' => 'Government ID',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf,jpg',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $document->id,
            'file_path' => 'applicant-documents/id.pdf',
            'original_name' => 'id.pdf',
            'file_size_kb' => 120,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.documents.destroy', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'This document requirement is linked to applicant uploads and cannot be deleted.');

        $this->assertDatabaseHas('required_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('applicant_documents', ['required_document_id' => $document->id]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.documents.update', $document), [
                'name' => 'Changed Identity Proof',
                'description' => 'Updated label',
                'is_mandatory' => false,
                'accepted_formats' => 'pdf',
                'max_size_kb' => 1024,
                'sort_order' => 2,
                'is_active' => false,
            ])
            ->assertSessionHasErrors('required_document');

        $document->refresh();
        $this->assertSame('Identity Proof', $document->name);
        $this->assertTrue((bool) $document->is_mandatory);
        $this->assertSame('pdf,jpg', $document->accepted_formats);
        $this->assertTrue((bool) $document->is_active);
    }

    public function test_used_required_document_can_still_receive_safe_description_updates(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create();
        $document = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Address Proof',
            'description' => 'Old note',
            'is_mandatory' => false,
            'accepted_formats' => 'pdf',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $document->id,
            'file_path' => 'applicant-documents/address.pdf',
            'original_name' => 'address.pdf',
            'file_size_kb' => 100,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.documents.update', $document), [
                'name' => 'Address Proof',
                'description' => 'Clarified instructions for applicants.',
                'is_mandatory' => false,
                'accepted_formats' => 'pdf',
                'max_size_kb' => 2048,
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame('Clarified instructions for applicants.', $document->fresh()->description);
        $this->assertSame(3, $document->fresh()->sort_order);
    }

    public function test_selection_step_with_sessions_or_scores_cannot_be_deleted_or_restructured(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create(['status' => 'shortlisted']);
        $step = SelectionProcessStep::create([
            'program_id' => $applicant->program_id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 50,
            'instructions' => 'Interview round',
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'session_name' => 'PI Panel A',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);
        ApplicantScore::create([
            'applicant_id' => $applicant->id,
            'selection_session_id' => $session->id,
            'selection_process_step_id' => $step->id,
            'scored_by' => $admin->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage' => 80,
            'is_final' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.steps.destroy', $step))
            ->assertRedirect()
            ->assertSessionHas('error', 'This selection step has sessions or scores and cannot be deleted.');

        $this->assertDatabaseHas('selection_process_steps', ['id' => $step->id]);
        $this->assertDatabaseHas('selection_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('applicant_scores', ['selection_process_step_id' => $step->id]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.steps.update', $step), [
                'name' => 'Personal Interview',
                'type' => 'gd',
                'step_order' => 2,
                'max_score' => 200,
                'weightage' => 60,
                'instructions' => 'Trying to restructure completed scoring.',
                'is_active' => false,
            ])
            ->assertSessionHasErrors('selection_step');

        $step->refresh();
        $this->assertSame('pi', $step->type);
        $this->assertSame(1, $step->step_order);
        $this->assertSame(100, $step->max_score);
        $this->assertTrue((bool) $step->is_active);
    }

    public function test_selection_session_with_assessment_history_cannot_be_deleted_or_rebounded(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $otherStep = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Group Discussion',
            'type' => 'gd',
            'step_order' => 2,
            'max_score' => 40,
            'weightage' => 40,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'PI Panel A',
            'scheduled_date' => today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);
        $applicant = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $applicant->id,
            'assigned_at' => now(),
            'attendance_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admission.sessions.show', $session))
            ->delete(route('admission.sessions.destroy', $session))
            ->assertRedirect(route('admission.sessions.show', $session))
            ->assertSessionHas('error', 'Cannot delete session: assigned candidates, attendance, panels, scores, or assessment history already exist.');

        $this->assertDatabaseHas('selection_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('session_applicants', ['selection_session_id' => $session->id, 'applicant_id' => $applicant->id]);

        $session->update(['status' => 'completed']);

        $this->actingAs($admin)
            ->put(route('admission.sessions.update', $session), [
                'selection_process_step_id' => $otherStep->id,
                'program_id' => $program->id,
                'session_name' => 'Rewritten Completed Session',
                'scheduled_date' => today()->addWeek()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '13:00',
                'venue' => 'Changed room',
                'max_candidates' => 20,
            ])
            ->assertRedirect(route('admission.sessions.show', $session))
            ->assertSessionHas('error', 'Only scheduled sessions can be edited.');

        $session->refresh();
        $this->assertSame($step->id, $session->selection_process_step_id);
        $this->assertSame('PI Panel A', $session->session_name);
    }

    public function test_selection_session_setup_requires_active_step_and_batch_in_selected_program(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $otherStep = SelectionProcessStep::create([
            'program_id' => $otherProgram->id,
            'name' => 'Group Discussion',
            'type' => 'gd',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $otherBatch = Batch::factory()->create(['program_id' => $otherProgram->id, 'status' => 'active']);

        $basePayload = [
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'session_name' => 'PI Scope Check',
            'scheduled_date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'venue' => 'Room 1',
            'max_candidates' => 20,
        ];

        $this->actingAs($admin)
            ->post(route('admission.sessions.store'), $basePayload + [
                'selection_process_step_id' => $otherStep->id,
            ])
            ->assertSessionHasErrors('selection_process_step_id');

        $this->actingAs($admin)
            ->post(route('admission.sessions.store'), array_merge($basePayload, [
                'selection_process_step_id' => $step->id,
                'batch_id' => $otherBatch->id,
                'session_name' => 'Wrong Batch Scope',
            ]))
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('selection_sessions', [
            'selection_process_step_id' => $otherStep->id,
            'program_id' => $program->id,
        ]);
        $this->assertDatabaseMissing('selection_sessions', [
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'batch_id' => $otherBatch->id,
        ]);
    }

    public function test_selection_session_assignment_requires_shortlisted_applicants_in_session_scope(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $otherBatch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'session_name' => 'PI Scoped Candidates',
            'scheduled_date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);

        $wrongProgram = Applicant::factory()->create([
            'program_id' => $otherProgram->id,
            'status' => 'shortlisted',
        ]);
        $wrongBatch = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $otherBatch->id,
            'status' => 'shortlisted',
        ]);
        $notShortlisted = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'submitted',
        ]);

        foreach ([$wrongProgram, $wrongBatch, $notShortlisted] as $applicant) {
            $this->actingAs($admin)
                ->post(route('admission.sessions.assign', $session), [
                    'applicant_ids' => [$applicant->id],
                ])
                ->assertSessionHasErrors('applicant_ids');

            $this->assertDatabaseMissing('session_applicants', [
                'selection_session_id' => $session->id,
                'applicant_id' => $applicant->id,
            ]);
        }

        $validApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
        ]);

        $this->actingAs($admin)
            ->post(route('admission.sessions.assign', $session), [
                'applicant_ids' => [$validApplicant->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '1 candidate(s) assigned to session.');

        $this->assertDatabaseHas('session_applicants', [
            'selection_session_id' => $session->id,
            'applicant_id' => $validApplicant->id,
        ]);
    }

    public function test_selection_session_candidate_operations_respect_assignment_scope(): void
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Scoped Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'session_name' => 'Scoped PI Candidate Ops',
            'scheduled_date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $assignedCounsellor->id,
        ]);

        $hiddenUser = User::factory()->create(['name' => 'Hidden Session Candidate']);
        $hiddenApplicant = Applicant::factory()->create([
            'user_id' => $hiddenUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => $assignedCounsellor->id,
        ]);
        $visibleUser = User::factory()->create(['name' => 'Visible Session Candidate']);
        $visibleApplicant = Applicant::factory()->create([
            'user_id' => $visibleUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => $peerCounsellor->id,
        ]);
        $unassignedUser = User::factory()->create(['name' => 'Unassigned Session Candidate']);
        $unassignedApplicant = Applicant::factory()->create([
            'user_id' => $unassignedUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => null,
        ]);

        foreach ([$hiddenApplicant, $visibleApplicant] as $applicant) {
            SessionApplicant::create([
                'selection_session_id' => $session->id,
                'applicant_id' => $applicant->id,
                'assigned_at' => now(),
                'attendance_status' => 'pending',
            ]);
        }

        $this->actingAs($peerCounsellor)
            ->get(route('admission.sessions.show', $session))
            ->assertOk()
            ->assertSee('Visible Session Candidate')
            ->assertSee('Unassigned Session Candidate')
            ->assertDontSee('Hidden Session Candidate')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 1 && $stats['pending'] === 1);

        $this->actingAs($peerCounsellor)
            ->post(route('admission.sessions.assign', $session), [
                'applicant_ids' => [$hiddenApplicant->id],
            ])
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.sessions.assign', $session), [
                'applicant_ids' => [$unassignedApplicant->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '1 candidate(s) assigned to session.');

        $this->actingAs($peerCounsellor)
            ->post(route('admission.sessions.attendance', $session), [
                'attendance' => [$hiddenApplicant->id => 'present'],
            ])
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->delete(route('admission.sessions.remove-applicant', [$session, $hiddenApplicant]))
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.sessions.attendance', $session), [
                'attendance' => [$visibleApplicant->id => 'present'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Attendance recorded successfully.');

        $this->assertDatabaseHas('session_applicants', [
            'selection_session_id' => $session->id,
            'applicant_id' => $hiddenApplicant->id,
            'attendance_status' => 'pending',
        ]);
        $this->assertDatabaseHas('session_applicants', [
            'selection_session_id' => $session->id,
            'applicant_id' => $visibleApplicant->id,
            'attendance_status' => 'present',
        ]);
        $this->assertDatabaseHas('session_applicants', [
            'selection_session_id' => $session->id,
            'applicant_id' => $unassignedApplicant->id,
        ]);
    }

    public function test_selection_session_candidate_and_completion_actions_respect_final_state_and_pending_attendance(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Group Discussion',
            'type' => 'gd',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'GD Batch',
            'scheduled_date' => today(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);
        $applicant = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        $assigned = SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $applicant->id,
            'assigned_at' => now(),
            'attendance_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admission.sessions.complete', $session))
            ->assertRedirect()
            ->assertSessionHas('error', 'Resolve all pending candidate attendance before completing the session.');

        $this->assertSame('scheduled', $session->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admission.sessions.attendance', $session), [
                'attendance' => [$applicant->id => 'present'],
                'panel_number' => [$applicant->id => 1],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Attendance recorded successfully.');

        $this->actingAs($admin)
            ->post(route('admission.sessions.complete', $session))
            ->assertRedirect()
            ->assertSessionHas('success', 'Session marked as completed.');

        $this->assertSame('completed', $session->fresh()->status);

        $newApplicant = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);

        $this->actingAs($admin)
            ->post(route('admission.sessions.assign', $session), ['applicant_ids' => [$newApplicant->id]])
            ->assertRedirect()
            ->assertSessionHas('error', 'Candidates can be assigned only to scheduled or ongoing sessions.');

        $this->actingAs($admin)
            ->delete(route('admission.sessions.remove-applicant', [$session, $applicant]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Candidates can be removed only from scheduled sessions before assessment activity starts.');

        $this->actingAs($admin)
            ->post(route('admission.sessions.attendance', $session), [
                'attendance' => [$applicant->id => 'absent'],
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Attendance can be recorded only for scheduled or ongoing sessions.');

        $this->assertDatabaseMissing('session_applicants', [
            'selection_session_id' => $session->id,
            'applicant_id' => $newApplicant->id,
        ]);
        $this->assertSame('present', $assigned->fresh()->attendance_status);
    }

    public function test_legacy_session_score_save_cannot_overwrite_finalized_scores(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $parameter = ScoringParameter::create([
            'selection_process_step_id' => $step->id,
            'name' => 'Communication',
            'max_score' => 50,
            'sort_order' => 1,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'PI Scores',
            'scheduled_date' => today(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'ongoing',
            'created_by' => $admin->id,
        ]);
        $applicant = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $applicant->id,
            'assigned_at' => now(),
            'attendance_status' => 'present',
        ]);
        $score = ApplicantScore::create([
            'applicant_id' => $applicant->id,
            'selection_session_id' => $session->id,
            'selection_process_step_id' => $step->id,
            'scored_by' => $admin->id,
            'parameter_scores' => [$parameter->id => 45],
            'total_score' => 45,
            'max_possible_score' => 50,
            'percentage' => 90,
            'remarks' => 'Original final score',
            'is_final' => true,
            'score_status' => 'finalized',
            'locked_at' => now(),
            'locked_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admission.sessions.scores', $session))
            ->post(route('admission.sessions.scores.save', $session), [
                'scores' => [
                    $applicant->id => [
                        'param_' . $parameter->id => 10,
                        'remarks' => 'Attempted rewrite',
                    ],
                ],
            ])
            ->assertRedirect(route('admission.sessions.scores', $session))
            ->assertSessionHasErrors('scores');

        $score->refresh();
        $this->assertSame(45.0, (float) $score->total_score);
        $this->assertSame('Original final score', $score->remarks);
        $this->assertSame('finalized', $score->score_status);
    }

    public function test_legacy_session_score_save_rejects_non_present_applicants_before_writing_scores(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Group Discussion',
            'type' => 'gd',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $parameter = ScoringParameter::create([
            'selection_process_step_id' => $step->id,
            'name' => 'Clarity',
            'max_score' => 50,
            'sort_order' => 1,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'GD Scores',
            'scheduled_date' => today(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'ongoing',
            'created_by' => $admin->id,
        ]);
        $applicant = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $applicant->id,
            'assigned_at' => now(),
            'attendance_status' => 'absent',
        ]);

        $this->actingAs($admin)
            ->from(route('admission.sessions.scores', $session))
            ->post(route('admission.sessions.scores.save', $session), [
                'scores' => [
                    $applicant->id => [
                        'param_' . $parameter->id => 40,
                        'remarks' => 'Should not be stored',
                    ],
                ],
            ])
            ->assertRedirect(route('admission.sessions.scores', $session))
            ->assertSessionHasErrors('scores');

        $this->assertDatabaseMissing('applicant_scores', [
            'applicant_id' => $applicant->id,
            'selection_session_id' => $session->id,
        ]);
    }

    public function test_call_letter_dispatch_is_blocked_for_inactive_sessions(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'Cancelled PI',
            'scheduled_date' => today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admission.sessions.dispatch-call-letters', $session))
            ->assertRedirect()
            ->assertSessionHas('error', 'Call letters can be dispatched only for scheduled or ongoing sessions.');

        $this->assertSame(0, Notification::where('type', 'call_letter')->count());
    }

    public function test_call_letter_dispatch_skips_final_absent_and_duplicate_candidates(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'PI Call Letters',
            'scheduled_date' => today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);

        $eligible = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        $final = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'enrolled']);
        $absent = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);

        foreach ([[$eligible, 'pending'], [$final, 'pending'], [$absent, 'absent']] as [$applicant, $attendance]) {
            SessionApplicant::create([
                'selection_session_id' => $session->id,
                'applicant_id' => $applicant->id,
                'assigned_at' => now(),
                'attendance_status' => $attendance,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('admission.sessions.dispatch-call-letters', $session))
            ->assertRedirect()
            ->assertSessionHas('success', 'Call letters dispatched to 1 candidate(s). 2 skipped.');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $eligible->user_id,
            'type' => 'call_letter',
            'action_url' => route('admission.applicants.call-letter', $eligible),
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $final->user_id, 'type' => 'call_letter']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $absent->user_id, 'type' => 'call_letter']);

        $this->actingAs($admin)
            ->post(route('admission.sessions.dispatch-call-letters', $session))
            ->assertRedirect()
            ->assertSessionHas('success', 'Call letters dispatched to 0 candidate(s). 3 skipped.');

        $this->assertSame(1, Notification::where('user_id', $eligible->user_id)->where('type', 'call_letter')->count());
    }

    public function test_scoring_parameter_with_selection_activity_cannot_be_deleted_or_recontracted(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create(['status' => 'shortlisted']);
        $step = SelectionProcessStep::create([
            'program_id' => $applicant->program_id,
            'name' => 'Group Discussion',
            'type' => 'gd',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 40,
            'instructions' => 'GD round',
            'is_active' => true,
        ]);
        $parameter = ScoringParameter::create([
            'selection_process_step_id' => $step->id,
            'name' => 'Communication',
            'max_score' => 50,
            'description' => 'Original communication rubric.',
            'sort_order' => 1,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'session_name' => 'GD Panel A',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);
        ApplicantScore::create([
            'applicant_id' => $applicant->id,
            'selection_session_id' => $session->id,
            'selection_process_step_id' => $step->id,
            'scored_by' => $admin->id,
            'parameter_scores' => [
                ['parameter_id' => $parameter->id, 'name' => 'Communication', 'score' => 42, 'max_score' => 50],
            ],
            'total_score' => 84,
            'max_possible_score' => 100,
            'percentage' => 84,
            'is_final' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.parameters.destroy', $parameter))
            ->assertRedirect()
            ->assertSessionHas('error', 'This scoring parameter belongs to a selection step with sessions or scores and cannot be deleted.');

        $this->assertDatabaseHas('scoring_parameters', ['id' => $parameter->id]);

        $this->actingAs($admin)
            ->post(route('admin.admission-config.parameters.store', $step), [
                'name' => 'New Leadership Potential',
                'max_score' => 20,
                'description' => 'Trying to add a rubric item after scoring.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'This selection step already has sessions or scores and cannot receive new scoring parameters. Create a new selection step version instead.');

        $this->actingAs($admin)
            ->post(route('admission.selection-process.parameters.store', $step), [
                'name' => 'New Confidence',
                'max_score' => 20,
                'description' => 'Trying to add a staff rubric item after scoring.',
                'sort_order' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'This selection step already has sessions or scores and cannot receive new scoring parameters. Create a new selection step version instead.');

        $this->assertSame(1, ScoringParameter::where('selection_process_step_id', $step->id)->count());

        $this->actingAs($admin)
            ->put(route('admission.selection-process.parameters.update', $parameter), [
                'name' => 'Changed Communication',
                'max_score' => 60,
                'description' => 'Trying to change scored rubric.',
                'sort_order' => 2,
            ])
            ->assertSessionHasErrors('scoring_parameter');

        $parameter->refresh();
        $this->assertSame('Communication', $parameter->name);
        $this->assertSame(50, (int) $parameter->max_score);
        $this->assertSame(1, (int) $parameter->sort_order);

        $this->actingAs($admin)
            ->delete(route('admission.selection-process.parameters.destroy', $parameter))
            ->assertRedirect()
            ->assertSessionHas('error', 'This scoring parameter belongs to a selection step with sessions or scores and cannot be deleted.');

        $this->assertDatabaseHas('scoring_parameters', ['id' => $parameter->id]);
    }

    public function test_admission_fee_installment_with_payment_history_cannot_be_deleted_or_financially_changed(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
        ]);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'due_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'transaction_reference' => 'ADM-CONFIG-LOCK',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.fee.destroy', $installment))
            ->assertRedirect()
            ->assertSessionHas('error', 'This installment is linked to admission payments and cannot be deleted.');

        $this->assertDatabaseHas('admission_fee_installments', ['id' => $installment->id]);
        $this->assertDatabaseHas('admission_payments', ['transaction_reference' => 'ADM-CONFIG-LOCK']);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.fee.update', $installment), [
                'name' => 'Admission Confirmation Fee',
                'amount' => 20000,
                'installment_number' => 2,
                'batch_id' => null,
                'due_date' => now()->addDays(10)->toDateString(),
                'description' => 'Trying to change paid installment.',
                'is_active' => false,
            ])
            ->assertSessionHasErrors('admission_fee_installment');

        $installment->refresh();
        $this->assertSame('15000.00', number_format((float) $installment->amount, 2, '.', ''));
        $this->assertSame(1, $installment->installment_number);
        $this->assertSame($applicant->batch_id, $installment->batch_id);
        $this->assertTrue((bool) $installment->is_active);

        $this->actingAs($admin)
            ->put(route('admission.fee-installments.update', $installment), [
                'name' => 'Admission Confirmation Fee',
                'amount' => 20000,
                'installment_number' => 1,
                'batch_id' => $applicant->batch_id,
                'due_date' => now()->addDays(10)->toDateString(),
                'description' => 'Staff route bypass attempt.',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('admission_fee_installment');

        $this->assertSame('15000.00', number_format((float) $installment->fresh()->amount, 2, '.', ''));
    }

    public function test_admission_fee_installment_batch_must_belong_to_selected_program(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $foreignBatch = Batch::factory()->create(['program_id' => $otherProgram->id]);

        $payload = [
            'name' => 'Admission Fee',
            'amount' => 12000,
            'installment_number' => 1,
            'batch_id' => $foreignBatch->id,
            'due_date' => now()->addDays(10)->toDateString(),
            'description' => 'Wrong program batch attempt.',
            'is_active' => true,
        ];

        $this->actingAs($admin)
            ->post(route('admin.admission-config.fee.store', $program), $payload)
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($admin)
            ->post(route('admission.fee-installments.store', $program), $payload)
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('admission_fee_installments', [
            'program_id' => $program->id,
            'batch_id' => $foreignBatch->id,
        ]);
    }

    public function test_admission_fee_installment_number_must_be_unique_per_program_batch_scope(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $existing = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'First Installment',
            'amount' => 10000,
            'installment_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.admission-config.fee.store', $program), [
                'name' => 'Duplicate Installment',
                'amount' => 15000,
                'installment_number' => 1,
                'batch_id' => $batch->id,
                'due_date' => now()->addDays(15)->toDateString(),
                'description' => 'Duplicate number attempt.',
            ])
            ->assertSessionHasErrors('installment_number');

        $this->actingAs($admin)
            ->put(route('admission.fee-installments.update', $existing), [
                'name' => 'Program Level Duplicate',
                'amount' => 15000,
                'installment_number' => 1,
                'batch_id' => null,
                'due_date' => now()->addDays(15)->toDateString(),
                'description' => 'Moving to a free program-level scope is allowed.',
                'is_active' => true,
            ])
            ->assertRedirect();

        $secondProgramLevel = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => null,
            'name' => 'Second Program Level Installment',
            'amount' => 20000,
            'installment_number' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.fee.update', $secondProgramLevel), [
                'name' => 'Conflicting Program Level Installment',
                'amount' => 20000,
                'installment_number' => 1,
                'batch_id' => null,
                'due_date' => null,
                'description' => 'Duplicate program-level number attempt.',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('installment_number');

        $this->assertSame(0, AdmissionFeeInstallment::where('program_id', $program->id)->where('batch_id', $batch->id)->count());
        $this->assertSame(2, AdmissionFeeInstallment::where('program_id', $program->id)->whereNull('batch_id')->count());
        $this->assertSame(2, $secondProgramLevel->fresh()->installment_number);
    }
}
