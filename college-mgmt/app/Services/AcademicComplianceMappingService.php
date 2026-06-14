<?php

namespace App\Services;

class AcademicComplianceMappingService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'compliance_mapping'): array { return parent::dashboard($type); }
}
