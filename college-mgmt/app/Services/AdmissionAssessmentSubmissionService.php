<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionAssessmentSubmissionService
{
    public function markReceived(Applicant $applicant, array $data, ?User $actor = null): int
    {
        return DB::table('admission_assessment_submissions')->insertGetId([
            'applicant_id' => $applicant->id,
            'panel_id' => $data['panel_id'] ?? null,
            'slot_id' => $data['slot_id'] ?? null,
            'submission_type' => $data['submission_type'] ?? 'case_analysis',
            'artifact_url' => $data['artifact_url'] ?? null,
            'status' => $data['status'] ?? 'received',
            'submitted_at' => $data['submitted_at'] ?? now(),
            'reviewed_by' => $actor?->id,
            'originality_flag' => (bool) ($data['originality_flag'] ?? false),
            'review_notes' => $data['review_notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
