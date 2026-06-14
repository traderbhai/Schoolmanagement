<?php

namespace App\Services;

class AcademicOnboardingReadinessService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'onboarding_readiness'): array { return parent::dashboard($type); }
}
