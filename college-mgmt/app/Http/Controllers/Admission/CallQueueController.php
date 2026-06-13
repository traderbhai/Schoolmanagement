<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionCallService;
use Illuminate\Http\Request;

class CallQueueController extends Controller
{
    public function index(Request $request, AdmissionCallService $service)
    {
        return view('admission.v003.call-queue', [
            'queue' => $service->queueFor($request->user(), $request->only(['program_id', 'source'])),
            'productivity' => $service->productivityFor($request->user()),
        ]);
    }

    public function log(Request $request, AdmissionCallService $service)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:50'],
            'disposition' => ['required', 'in:connected,not_reachable,call_back_later,interested,not_interested,wrong_number,duplicate,converted_to_applicant,escalated_to_counsellor'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'next_followup_at' => ['nullable', 'date'],
            'outcome_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $service->logCall($subject, $request->user(), $data);

        return back()->with('success', 'Call logged and next action updated.');
    }
}
