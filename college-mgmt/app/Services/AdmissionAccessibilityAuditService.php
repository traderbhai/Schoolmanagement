<?php

namespace App\Services;

class AdmissionAccessibilityAuditService
{
    public function checklist(): array
    {
        return [
            ['surface' => 'Assessment Control Room', 'status' => 'reviewed', 'note' => 'Tables use captions/labels, action buttons have text labels, and mobile overflow is checked.'],
            ['surface' => 'Counsellor Desk', 'status' => 'reviewed', 'note' => 'Quick actions use visible labels and compact cards preserve reading order.'],
            ['surface' => 'Integrations', 'status' => 'reviewed', 'note' => 'Provider status and retry actions are text-labeled.'],
            ['surface' => 'Automation Simulation', 'status' => 'reviewed', 'note' => 'Preview tables expose route and action text without icon-only controls.'],
        ];
    }
}
