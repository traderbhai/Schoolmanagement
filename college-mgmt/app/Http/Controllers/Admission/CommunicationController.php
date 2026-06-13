<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionCommunicationService;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index()
    {
        return view('admission.v003.communication', [
            'templates' => AdmissionCommunicationTemplate::latest()->get(),
            'logs' => AdmissionCommunicationLog::with('template')->latest()->limit(50)->get(),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        AdmissionCommunicationTemplate::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:email,internal,sms,whatsapp'],
            'purpose' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Communication template saved.');
    }

    public function send(Request $request, AdmissionCommunicationService $service)
    {
        $data = $request->validate([
            'template_id' => ['required', 'exists:admission_communication_templates,id'],
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
        ]);
        $subject = $data['subject_type'] === 'lead'
            ? Lead::findOrFail($data['subject_id'])
            : Applicant::findOrFail($data['subject_id']);
        $template = AdmissionCommunicationTemplate::findOrFail($data['template_id']);
        $log = $service->queue($subject, $template, $request->user());

        return back()->with('success', "Communication queued as #{$log->id}.");
    }

    public function dispatch(AdmissionCommunicationService $service)
    {
        $count = $service->dispatchQueued();

        return back()->with('success', "{$count} queued communications marked sent.");
    }
}
