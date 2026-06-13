<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Services\AdmissionAssessmentNormalizationService;
use Illuminate\Http\Request;

class AssessmentNormalizationController extends Controller
{
    public function index(AdmissionAssessmentNormalizationService $service)
    {
        return view('admission.v0037.assessment-normalization', ['dashboard' => $service->dashboard()]);
    }

    public function run(Request $request, AdmissionAssessmentNormalizationService $service)
    {
        AdmissionAssessmentPanel::latest()->limit(20)->get()->each(fn ($panel) => $service->normalizePanel($panel));

        return back()->with('success', 'Assessment score normalization refreshed.');
    }
}
