<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionDataQualityFlag;
use App\Models\Lead;
use App\Models\Applicant;
use App\Services\AdmissionDataQualityService;
use Illuminate\Http\Request;

class DataQualityController extends Controller
{
    public function index()
    {
        return view('admission.v003.data-quality', [
            'flags' => AdmissionDataQualityFlag::latest()->limit(100)->get(),
        ]);
    }

    public function scan(Request $request, AdmissionDataQualityService $service)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
        ]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $flags = $subject instanceof Lead ? $service->scanLead($subject) : $service->scanApplicant($subject);

        return back()->with('success', $flags->count() . ' data-quality flag(s) open.');
    }

    public function resolve(AdmissionDataQualityFlag $flag, Request $request)
    {
        $flag->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('success', 'Data-quality flag resolved.');
    }
}
