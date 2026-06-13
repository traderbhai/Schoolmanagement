<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\AdmissionAccessPolicyService;
use App\Services\AdmissionHandoffService;
use App\Services\AdmissionSensitiveAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HandoffController extends Controller
{
    public function index(Request $request)
    {
        app(AdmissionAccessPolicyService::class)->authorize($request->user(), 'read.handoff');

        $query = DB::table('admission_handoff_records')
            ->leftJoin('applicants', 'applicants.id', '=', 'admission_handoff_records.applicant_id')
            ->leftJoin('users', 'users.id', '=', 'applicants.user_id')
            ->select('admission_handoff_records.*', 'applicants.application_number', 'users.name as applicant_name')
            ->when($request->filled('status'), fn ($q) => $q->where('admission_handoff_records.status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('applicants.application_number', 'like', '%'.$request->q.'%')
                    ->orWhere('users.name', 'like', '%'.$request->q.'%');
            }))
            ->latest('admission_handoff_records.updated_at');

        return view('admission.v0039.handoff', [
            'records' => $query->paginate(20)->withQueryString(),
            'status' => $request->status,
            'q' => $request->q,
            'counts' => DB::table('admission_handoff_records')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function refresh(int $applicantId, Request $request, AdmissionHandoffService $service)
    {
        $applicant = Applicant::findOrFail($applicantId);
        app(AdmissionAccessPolicyService::class)->authorize($request->user(), 'handoff.refresh', $applicant);
        $service->ensure($applicant, $applicant->enrollmentConfirmation, $request->user());

        return back()->with('success', 'Handoff readiness refreshed.');
    }

    public function markHandedOff(int $handoffId, Request $request, AdmissionHandoffService $service, AdmissionSensitiveAuditService $audit)
    {
        app(AdmissionAccessPolicyService::class)->authorize($request->user(), 'handoff.approve');
        $before = (array) DB::table('admission_handoff_records')->where('id', $handoffId)->first();
        $service->markHandedOff($handoffId, $request->user());
        $after = (array) DB::table('admission_handoff_records')->where('id', $handoffId)->first();
        $audit->record('admission_handoff_completed', null, $request->user(), 'Admission handoff sent to Academics/PMC.', $before, $after);

        return back()->with('success', 'Record marked handed off to Academics/PMC.');
    }

    public function returnForCorrection(int $handoffId, Request $request, AdmissionHandoffService $service, AdmissionSensitiveAuditService $audit)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        app(AdmissionAccessPolicyService::class)->authorize($request->user(), 'handoff.return');
        $before = (array) DB::table('admission_handoff_records')->where('id', $handoffId)->first();
        $service->returnForCorrection($handoffId, $request->user(), $data['reason']);
        $after = (array) DB::table('admission_handoff_records')->where('id', $handoffId)->first();
        $audit->record('admission_handoff_returned', null, $request->user(), $data['reason'], $before, $after);

        return back()->with('success', 'Record returned for admission correction.');
    }
}
