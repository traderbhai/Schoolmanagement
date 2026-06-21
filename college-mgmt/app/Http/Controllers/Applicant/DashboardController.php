<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\AdmissionFormConfig;
use App\Models\EnrollmentConfirmation;
use App\Models\OfferLetter;
use App\Services\AdmissionApplicantReadinessService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(AdmissionApplicantReadinessService $readiness)
    {
        $applicant = auth()->user()->applicant()->with(['program', 'batch', 'documents', 'payments'])->firstOrFail();
        $formConfig = AdmissionFormConfig::where('program_id', $applicant->program_id)->first();
        $sections = $formConfig ? $formConfig->form_sections : AdmissionFormConfig::getDefaultSections();

        $completedSections = 0;
        $sectionKeys = ['personal', 'academic', 'family', 'additional'];
        foreach ($sectionKeys as $key) {
            if (!empty($applicant->getFormDataForSection($key))) {
                $completedSections++;
            }
        }

        $offerLetter = OfferLetter::where('applicant_id', $applicant->id)->first();
        $enrollment  = EnrollmentConfirmation::where('applicant_id', $applicant->id)->first();
        $checklist = $readiness->checklist($applicant);

        return view('applicant.dashboard', compact('applicant', 'sections', 'completedSections', 'offerLetter', 'enrollment', 'checklist'));
    }
}
