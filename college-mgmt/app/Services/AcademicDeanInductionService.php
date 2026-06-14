<?php

namespace App\Services;

class AcademicDeanInductionService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'induction_onboarding'): array { return parent::dashboard($type); }
}
