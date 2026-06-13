<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionSelectionCommitteeService
{
    public function decide(Applicant $applicant, string $decision, string $reason, ?User $actor = null, array $data = []): int
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Decision reason is required.']);
        }

        $decisionId = DB::table('admission_selection_committee_decisions')->insertGetId([
            'applicant_id' => $applicant->id,
            'panel_id' => $data['panel_id'] ?? null,
            'decision' => $decision,
            'reason' => $reason,
            'decided_by' => $actor?->id,
            'decided_at' => now(),
            'normalized_score' => $data['normalized_score'] ?? null,
            'metadata' => json_encode(['documents_ready' => $data['documents_ready'] ?? false, 'payment_ready' => $data['payment_ready'] ?? false]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (in_array($decision, ['selected', 'waitlist', 'rejected', 'hold'], true)) {
            $applicant->update(['status' => $decision === 'waitlist' ? 'shortlisted' : $decision]);
        }

        DB::table('admission_sensitive_audit_events')->insert([
            'action' => 'selection_committee_decision',
            'subject_type' => Applicant::class,
            'subject_id' => $applicant->id,
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'after' => json_encode(['decision' => $decision]),
            'metadata' => json_encode(['v' => '0.038']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $decisionId;
    }
}
