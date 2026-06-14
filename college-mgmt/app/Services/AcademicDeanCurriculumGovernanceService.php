<?php

namespace App\Services;

class AcademicDeanCurriculumGovernanceService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'curriculum_governance'): array { return parent::dashboard($type); }
}
