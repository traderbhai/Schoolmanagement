<?php

namespace App\Services;

class AcademicSyllabusVersioningService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'syllabus_version'): array { return parent::dashboard($type); }
}
