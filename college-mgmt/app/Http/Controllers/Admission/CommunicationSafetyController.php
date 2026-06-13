<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionCommunicationSafetyService;
use App\Services\AdmissionConsentService;
use App\Services\AdmissionTemplateApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationSafetyController extends Controller
{
    public function index()
    {
        return view('admission.v0038.communication-safety', [
            'templates' => AdmissionCommunicationTemplate::latest()->limit(25)->get(),
            'consents' => DB::table('admission_consent_records')->latest()->limit(40)->get(),
            'approvals' => DB::table('admission_template_approvals')->latest()->limit(25)->get(),
            'previews' => DB::table('admission_bulk_send_previews')->latest()->limit(25)->get(),
            'quietHours' => DB::table('admission_quiet_hour_rules')->where('is_active', true)->get(),
            'audit' => DB::table('admission_sensitive_audit_events')->latest()->limit(25)->get(),
        ]);
    }

    public function consent(Request $request, AdmissionConsentService $service)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
            'channel' => ['required', 'in:email,sms,whatsapp,call'],
            'status' => ['required', 'in:opt_in,opt_out'],
            'reason' => ['nullable', 'string'],
        ]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $service->set($subject, $data['channel'], $data['status'], $request->user(), $data['reason'] ?? null);
        return back()->with('success', 'Consent preference updated.');
    }

    public function requestApproval(int $templateId, Request $request, AdmissionTemplateApprovalService $service)
    {
        $service->request(AdmissionCommunicationTemplate::findOrFail($templateId), $request->user());
        return back()->with('success', 'Template approval request created.');
    }

    public function approveTemplate(int $approvalId, Request $request, AdmissionTemplateApprovalService $service)
    {
        $service->approve($approvalId, $request->user());
        return back()->with('success', 'Template approved for sending.');
    }

    public function preview(Request $request, AdmissionCommunicationSafetyService $service)
    {
        $data = $request->validate(['template_id' => ['required', 'exists:admission_communication_templates,id'], 'audience' => ['required', 'in:leads,applicants']]);
        $template = AdmissionCommunicationTemplate::findOrFail($data['template_id']);
        $subjects = $data['audience'] === 'leads' ? Lead::limit(50)->get() : Applicant::with('user')->limit(50)->get();
        $preview = $service->preview($template, $subjects, $request->user(), ['audience' => $data['audience']]);
        return back()->with('success', "Preview complete: {$preview->audience_count} audience, {$preview->blocked_count} blocked, {$preview->duplicate_count} duplicate.");
    }
}
