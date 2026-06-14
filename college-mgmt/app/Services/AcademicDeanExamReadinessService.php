<?php

namespace App\Services;

class AcademicDeanExamReadinessService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'exam_readiness'): array { return parent::dashboard($type); }
}
