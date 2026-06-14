<?php

namespace App\Services;

class AcademicDeanStudentSuccessService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'student_success'): array { return parent::dashboard($type); }
}
