<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Services\AdmissionApplicantReadinessService;

class ChecklistController extends Controller
{
    public function index(AdmissionApplicantReadinessService $readiness)
    {
        $applicant = auth()->user()->applicant()->with(['program', 'batch'])->firstOrFail();
        $checklist = $readiness->checklist($applicant);

        return view('applicant.checklist', compact('applicant', 'checklist'));
    }
}
