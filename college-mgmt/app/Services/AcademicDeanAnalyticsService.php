<?php

namespace App\Services;

use App\Models\AcademicDeanOperatingRecord;
use App\Models\AcademicDeanRiskSnapshot;

class AcademicDeanAnalyticsService
{
    public function dashboard(): array
    {
        return [
            'riskTrend' => AcademicDeanRiskSnapshot::latest('snapshot_date')->limit(12)->get(),
            'recordSummary' => AcademicDeanOperatingRecord::selectRaw('record_type, status, count(*) as total')
                ->groupBy('record_type', 'status')
                ->orderBy('record_type')
                ->get(),
            'charts' => [
                'Program risk trend',
                'Approval SLA',
                'Action closure',
                'Faculty workload',
                'Student interventions',
                'OBE/IQAC gaps',
                'Induction readiness',
            ],
        ];
    }
}
