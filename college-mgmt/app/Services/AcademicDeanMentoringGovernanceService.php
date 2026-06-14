<?php

namespace App\Services;

class AcademicDeanMentoringGovernanceService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'mentoring_governance'): array { return parent::dashboard($type); }
}
