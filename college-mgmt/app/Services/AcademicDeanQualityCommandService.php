<?php

namespace App\Services;

class AcademicDeanQualityCommandService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'quality_command'): array { return parent::dashboard($type); }
}
