<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Services\AdmissionAssessmentSchedulingService;
use Illuminate\Http\Request;

class AssessmentScheduleConflictController extends Controller
{
    public function index(Request $request, AdmissionAssessmentSchedulingService $service)
    {
        return view('admission.v0037.assessment-schedule-conflicts', [
            'dashboard' => $service->dashboard(),
        ]);
    }

    public function refresh(AdmissionAssessmentPanel $panel, AdmissionAssessmentSchedulingService $service)
    {
        $count = $service->detectConflictsForPanel($panel)->count();

        return back()->with('success', $count . ' scheduling issue(s) reviewed for ' . $panel->name . '.');
    }
}
