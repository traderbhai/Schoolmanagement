<?php

namespace App\Services;

use App\Models\AcademicDeanApprovalItem;

class AcademicDeanApprovalPolicyService
{
    public function requiresReason(AcademicDeanApprovalItem $item, string $status): bool
    {
        return in_array($status, ['rejected', 'returned', 'override_approved'], true) || in_array($item->risk_level, ['high', 'critical'], true);
    }
}
