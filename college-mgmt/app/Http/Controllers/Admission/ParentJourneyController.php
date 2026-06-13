<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionParentJourney;
use App\Models\Applicant;
use App\Services\AdmissionParentJourneyService;
use Illuminate\Http\Request;

class ParentJourneyController extends Controller
{
    public function index(AdmissionParentJourneyService $service)
    {
        return view('admission.v0037.parent-journeys', ['dashboard' => $service->dashboard()]);
    }

    public function reminder(AdmissionParentJourney $journey, AdmissionParentJourneyService $service)
    {
        $service->createReminder($journey, request()->user());

        return back()->with('success', 'Parent/guardian reminder created.');
    }
}
