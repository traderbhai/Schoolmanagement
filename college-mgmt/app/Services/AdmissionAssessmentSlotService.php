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

    public function requestReschedule(int $assignmentId, Applicant $applicant, string $reason, ?int $requestedSlotId = null): int
    {
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

    public function reviewReschedule(int $requestId, string $status, User $actor): void
    {
        DB::table('admission_assessment_reschedule_requests')->where('id', $requestId)->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);
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
}
