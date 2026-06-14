<?php

namespace App\Services;

class AcademicAuditEvidenceService extends AcademicDeanOperatingRecordService
{
    public function dashboard(string $type = 'audit_evidence'): array { return parent::dashboard($type); }
}
