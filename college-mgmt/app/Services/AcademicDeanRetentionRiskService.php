<?php

namespace App\Services;

class AcademicDeanRetentionRiskService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'retention_risk'): array { return parent::dashboard($type); }
}
