<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionAccessibilityAuditService;

class AccessibilityAuditController extends Controller
{
    public function __invoke(AdmissionAccessibilityAuditService $service)
    {
        return view('admission.v0037.accessibility-audit', ['items' => $service->checklist()]);
    }
}
