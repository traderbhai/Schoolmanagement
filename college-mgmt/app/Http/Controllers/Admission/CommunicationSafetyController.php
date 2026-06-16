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
        $consents = DB::table('admission_consent_records')->latest()->limit(40)->get();
        $blocked = DB::table('admission_blocked_communications')->latest()->limit(40)->get();
        $subjectLabels = $this->subjectLabels($consents->merge($blocked));

        return view('admission.v0038.communication-safety', [
            'templates' => AdmissionCommunicationTemplate::latest()->limit(25)->get(),
            'leads' => Lead::orderBy('name')->limit(75)->get(['id', 'name', 'email', 'phone']),
            'applicants' => Applicant::with('user')->latest()->limit(75)->get(['id', 'user_id', 'application_number', 'status']),
            'consents' => $consents,
            'subjectLabels' => $subjectLabels,
            'approvals' => DB::table('admission_template_approvals as approvals')
                ->leftJoin('admission_communication_templates as templates', 'templates.id', '=', 'approvals.template_id')
                ->select('approvals.*', 'templates.name as template_name', 'templates.channel as template_channel')
                ->latest('approvals.created_at')
                ->limit(25)
                ->get(),
            'previews' => DB::table('admission_bulk_send_previews')->latest()->limit(25)->get(),
            'blocked' => $blocked,
            'quietHours' => DB::table('admission_quiet_hour_rules')->where('is_active', true)->get(),
            'audit' => DB::table('admission_sensitive_audit_events')->latest()->limit(25)->get(),
        ]);
    }

    public function consent(Request $request, AdmissionConsentService $service)
    {
        $data = $request->validate([
            'subject_key' => ['nullable', 'string'],
            'subject_type' => ['required_without:subject_key', 'in:lead,applicant'],
            'subject_id' => ['required_without:subject_key', 'integer'],
            'channel' => ['required', 'in:email,sms,whatsapp,call'],
            'status' => ['required', 'in:opt_in,opt_out'],
            'reason' => ['nullable', 'string'],
        ]);

        if (! empty($data['subject_key'])) {
            [$data['subject_type'], $data['subject_id']] = explode(':', $data['subject_key'], 2) + [null, null];
        }

        abort_unless(in_array($data['subject_type'], ['lead', 'applicant'], true) && ctype_digit((string) $data['subject_id']), 422);

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

    private function subjectLabels($records): array
    {
        $leadIds = $records
            ->where('subject_type', Lead::class)
            ->pluck('subject_id')
            ->filter()
            ->unique();
        $applicantIds = $records
            ->where('subject_type', Applicant::class)
            ->pluck('subject_id')
            ->filter()
            ->unique();

        $leadLabels = Lead::whereIn('id', $leadIds)
            ->get(['id', 'name', 'email', 'phone'])
            ->mapWithKeys(fn (Lead $lead) => [
                Lead::class . ':' . $lead->id => trim(($lead->name ?: 'Unnamed lead') . ' - ' . ($lead->email ?: $lead->phone ?: 'No contact on file')),
            ]);

        $applicantLabels = Applicant::with('user')
            ->whereIn('id', $applicantIds)
            ->get(['id', 'user_id', 'application_number'])
            ->mapWithKeys(fn (Applicant $applicant) => [
                Applicant::class . ':' . $applicant->id => trim(($applicant->user?->name ?: 'Applicant') . ' - ' . $applicant->application_number),
            ]);

        return $leadLabels->merge($applicantLabels)->all();
    }
}
