<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionCallAttemptService;
use App\Services\AdmissionCallingDeskService;
use App\Services\AdmissionCallQueueSelectorService;
use Illuminate\Http\Request;

class CallingDeskController extends Controller
{
    public function index(Request $request, AdmissionCallingDeskService $service)
    {
        return view('admission.v0038.calling-desk', $service->dashboard($request->user()));
    }

    public function outcome(Request $request, AdmissionCallAttemptService $service)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
            'disposition' => ['required', 'string'],
            'outcome' => ['nullable', 'string'],
            'retry_due_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer'],
            'script_template_id' => ['nullable', 'exists:admission_script_templates,id'],
            'script_results' => ['nullable', 'array'],
            'next_action' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $subject = $data['subject_type'] === 'lead'
            ? Lead::findOrFail($data['subject_id'])
            : Applicant::findOrFail($data['subject_id']);

        $service->record($subject, $request->user(), $data);

        return back()->with('success', 'Call outcome saved and next action updated.');
    }

    public function skip(Request $request, AdmissionCallQueueSelectorService $service)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
            'reason' => ['required', 'string'],
        ]);

        $subject = $data['subject_type'] === 'lead'
            ? Lead::findOrFail($data['subject_id'])
            : Applicant::findOrFail($data['subject_id']);

        $service->skip($subject, $request->user(), $data['reason']);

        return back()->with('success', 'Record skipped for this calling session.');
    }
}
