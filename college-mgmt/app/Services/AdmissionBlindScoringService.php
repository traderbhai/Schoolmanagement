<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\AdmissionBlindScoringAlias;
use App\Models\User;

class AdmissionBlindScoringService
{
    public function aliasFor(AdmissionAssessmentPanelAssignment $assignment): AdmissionBlindScoringAlias
    {
        return AdmissionBlindScoringAlias::firstOrCreate(
            ['panel_id' => $assignment->panel_id, 'applicant_id' => $assignment->applicant_id],
            ['alias_code' => 'CAND-' . str_pad((string) $assignment->applicant_id, 4, '0', STR_PAD_LEFT), 'is_active' => true, 'metadata' => ['v' => '0.037']]
        );
    }

    public function displayCandidate(AdmissionAssessmentPanelAssignment $assignment, User $viewer): array
    {
        $isLeadership = $viewer->hasAnyRole(['admin', 'admission_head', 'admission_manager', 'admission_director']);
        $alias = $this->aliasFor($assignment);
        $assignment->loadMissing('applicant.user', 'applicant.program');

        return [
            'code' => $alias->alias_code,
            'name' => $isLeadership ? ($assignment->applicant?->user?->name ?? $alias->alias_code) : $alias->alias_code,
            'masked' => ! $isLeadership,
            'program' => $assignment->applicant?->program?->name,
            'status' => $assignment->score_status,
        ];
    }
}
