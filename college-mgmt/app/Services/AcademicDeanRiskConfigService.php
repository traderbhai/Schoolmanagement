<?php

namespace App\Services;

use App\Models\AcademicDeanRiskThreshold;

class AcademicDeanRiskConfigService
{
    public function threshold(string $dimension = 'overall'): AcademicDeanRiskThreshold
    {
        return AcademicDeanRiskThreshold::firstOrCreate(
            ['dimension' => $dimension, 'scope_type' => 'department', 'scope_id' => null],
            ['medium_threshold' => 20, 'high_threshold' => 40, 'critical_threshold' => 70, 'is_active' => true]
        );
    }

    public function band(int $score, string $dimension = 'overall'): string
    {
        $threshold = $this->threshold($dimension);
        return $score >= $threshold->critical_threshold ? 'critical' : ($score >= $threshold->high_threshold ? 'high' : ($score >= $threshold->medium_threshold ? 'medium' : 'low'));
    }
}
