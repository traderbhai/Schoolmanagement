<?php

namespace App\Services;

class AcademicDeanFacultyWorkloadService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'faculty_workload'): array { return parent::dashboard($type); }
}
