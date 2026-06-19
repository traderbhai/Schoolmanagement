<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanel;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionAssessmentSlotService
{
    public function create(array $data): int
    {
        return DB::table('admission_assessment_slots')->insertGetId([
            'selection_session_id' => $data['selection_session_id'] ?? null,
            'panel_id' => $data['panel_id'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'slot_code' => $data['slot_code'] ?? 'SLOT-' . now()->format('His'),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => $data['capacity'] ?? 1,
            'venue' => $data['venue'] ?? null,
            'online_link' => $data['online_link'] ?? null,
            'status' => $data['status'] ?? 'open',
            'metadata' => json_encode(['v' => '0.038']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function assignApplicant(int $slotId, Applicant $applicant, ?User $actor = null): void
    {
        $slot = DB::table('admission_assessment_slots')->where('id', $slotId)->first();
        $assigned = DB::table('admission_assessment_slot_assignments')->where('slot_id', $slotId)->count();
        if ($slot && $assigned >= $slot->capacity) {
            throw ValidationException::withMessages(['slot_id' => 'Assessment slot capacity is already full.']);
        }

        DB::table('admission_assessment_slot_assignments')->updateOrInsert(
            ['slot_id' => $slotId, 'applicant_id' => $applicant->id],
            [
                'status' => 'assigned',
                'assigned_by' => $actor?->id,
                'metadata' => json_encode(['v' => '0.038']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function bulkAssign(int $slotId, iterable $applicants, ?User $actor = null): array
    {
        $assigned = 0;
        $blocked = [];

        foreach ($applicants as $applicant) {
            try {
                $this->assignApplicant($slotId, $applicant, $actor);
                $assigned++;
            } catch (\Throwable $e) {
                $blocked[] = ['applicant_id' => $applicant->id, 'reason' => $e->getMessage()];
                break;
            }
        }

        return compact('assigned', 'blocked');
    }

    public function requestReschedule(int $assignmentId, Applicant $applicant, string $reason, ?int $requestedSlotId = null): int
    {
        if ($requestedSlotId !== null) {
            $this->assertSlotMatchesApplicantScope($requestedSlotId, $applicant);
        }

        return DB::table('admission_assessment_reschedule_requests')->insertGetId([
            'slot_assignment_id' => $assignmentId,
            'applicant_id' => $applicant->id,
            'requested_slot_id' => $requestedSlotId,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertSlotMatchesApplicantScope(int $slotId, Applicant $applicant): void
    {
        $slot = DB::table('admission_assessment_slots')
            ->leftJoin('selection_sessions', 'selection_sessions.id', '=', 'admission_assessment_slots.selection_session_id')
            ->where('admission_assessment_slots.id', $slotId)
            ->select(
                'admission_assessment_slots.id',
                'admission_assessment_slots.status',
                'selection_sessions.program_id',
                'selection_sessions.batch_id'
            )
            ->first();

        if (! $slot || $slot->status !== 'open') {
            throw ValidationException::withMessages(['requested_slot_id' => 'Requested assessment slot is not available.']);
        }

        if ($slot->program_id !== null && (int) $slot->program_id !== (int) $applicant->program_id) {
            throw ValidationException::withMessages(['requested_slot_id' => 'Requested assessment slot is not available for this application program.']);
        }

        if ($slot->batch_id !== null && (int) $slot->batch_id !== (int) $applicant->batch_id) {
            throw ValidationException::withMessages(['requested_slot_id' => 'Requested assessment slot is not available for this application batch.']);
        }
    }

    public function reviewReschedule(int $requestId, string $status, User $actor): void
    {
        $request = DB::table('admission_assessment_reschedule_requests')->where('id', $requestId)->first();
        DB::table('admission_assessment_reschedule_requests')->where('id', $requestId)->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request && $status === 'approved' && $request->requested_slot_id) {
            DB::table('admission_assessment_slot_assignments')->where('id', $request->slot_assignment_id)->update([
                'slot_id' => $request->requested_slot_id,
                'status' => 'rescheduled',
                'updated_at' => now(),
            ]);
        }
    }

    public function inviteEvaluators(AdmissionAssessmentPanel $panel): void
    {
        foreach ($panel->members as $member) {
            DB::table('admission_evaluator_invitations')->updateOrInsert(
                ['panel_id' => $panel->id, 'user_id' => $member->user_id],
                ['status' => 'pending', 'invited_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function evaluatorResponse(int $invitationId, string $status, ?string $note = null): void
    {
        DB::table('admission_evaluator_invitations')->where('id', $invitationId)->update([
            'status' => $status,
            'responded_at' => now(),
            'response_note' => $note,
            'updated_at' => now(),
        ]);
    }

    public function checkIn(int $assignmentId, string $status, ?User $actor = null): void
    {
        $allowed = ['invited', 'confirmed', 'checked_in', 'waiting', 'in_progress', 'completed', 'no_show', 'rescheduled', 'cancelled'];
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown assessment lifecycle status.']);
        }

        DB::table('admission_assessment_slot_assignments')->where('id', $assignmentId)->update([
            'status' => $status,
            'checked_in_at' => $status === 'checked_in' ? now() : DB::raw('checked_in_at'),
            'metadata' => json_encode(['updated_by' => $actor?->id, 'v' => '0.039_check_in']),
            'updated_at' => now(),
        ]);
    }

    public function replaceEvaluator(int $invitationId, int $replacementUserId, ?User $actor = null): void
    {
        $invitation = DB::table('admission_evaluator_invitations')->where('id', $invitationId)->first();
        if (! $invitation) {
            return;
        }

        DB::table('admission_evaluator_invitations')->where('id', $invitationId)->update([
            'status' => 'replaced',
            'response_note' => trim(($invitation->response_note ? $invitation->response_note.'; ' : '').'Replaced by user '.$replacementUserId),
            'updated_at' => now(),
        ]);

        DB::table('admission_evaluator_invitations')->updateOrInsert(
            ['panel_id' => $invitation->panel_id, 'user_id' => $replacementUserId],
            ['status' => 'pending', 'invited_at' => now(), 'response_note' => 'Replacement evaluator', 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
