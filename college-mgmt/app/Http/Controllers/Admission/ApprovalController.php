<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApproval;
use App\Models\Lead;
use App\Models\Applicant;
use App\Services\AdmissionApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index()
    {
        return view('admission.v003.approvals', [
            'approvals' => AdmissionApproval::latest()->limit(100)->get(),
        ]);
    }

    public function request(Request $request, AdmissionApprovalService $service)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'max:60'],
            'after_json' => ['required', 'json'],
            'reason' => ['nullable', 'string'],
        ]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $service->request($subject, $data['action'], $request->user(), json_decode($data['after_json'], true), $data['reason'] ?? null);

        return back()->with('success', 'Approval requested.');
    }

    public function approve(AdmissionApproval $approval, Request $request, AdmissionApprovalService $service)
    {
        $service->approve($approval, $request->user());

        return back()->with('success', 'Approval completed.');
    }

    public function reject(AdmissionApproval $approval, Request $request, AdmissionApprovalService $service)
    {
        $service->reject($approval, $request->user(), $request->get('reason'));

        return back()->with('success', 'Approval rejected.');
    }
}
