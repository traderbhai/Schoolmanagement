<?php

namespace App\Services;

class AcademicDeanFacultyPerformanceService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'faculty_performance'): array { return parent::dashboard($type); }
}
