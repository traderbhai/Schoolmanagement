<?php

namespace App\Services;

use App\Models\AdmissionLeadScore;
use App\Models\Lead;
use App\Models\User;

class AdmissionLeadScoringService
{
    public function score(Lead $lead, ?User $actor = null, array $overrides = []): AdmissionLeadScore
    {
        $parts = [
            'source_quality' => $this->sourceScore($lead->source),
            'priority' => match ($lead->priority) {
                'urgent' => 25, 'high' => 18, 'low' => 3, default => 10,
            },
            'engagement' => min(20, $lead->followUps()->count() * 5 + $lead->callLogs()->count() * 5 + $lead->communicationLogs()->count() * 3),
            'response_speed' => $lead->last_contacted_at ? 15 : 0,
            'manual_override' => (int) ($overrides['manual_priority_points'] ?? 0),
        ];

        $score = max(0, min(100, array_sum($parts)));
        $band = $score >= 75 ? 'hot' : ($score >= 45 ? 'warm' : 'cold');

        $record = AdmissionLeadScore::create([
            'lead_id' => $lead->id,
            'score' => $score,
            'band' => $band,
            'explanation' => $parts,
            'scored_by' => $actor?->id,
            'scored_at' => now(),
        ]);

        $lead->update(['score_band' => $band]);

        return $record;
    }

    private function sourceScore(?string $source): int
    {
        return match ($source) {
            'referral' => 25,
            'web_form' => 20,
            'event' => 18,
            'agent' => 15,
            'social_media' => 12,
            default => 8,
        };
    }
}
