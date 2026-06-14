<?php

namespace App\Services;

class AcademicObeActionPlanService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'obe_action_plan'): array { return parent::dashboard($type); }
}
