<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionWalkIn;
use App\Models\Program;
use App\Models\User;
use App\Services\AdmissionWalkInService;
use Illuminate\Http\Request;

class WalkInController extends Controller
{
    public function index(AdmissionWalkInService $service)
    {
        return view('admission.v0031.walk-ins', [
            'walkIns' => AdmissionWalkIn::with(['program', 'counsellor', 'lead'])->latest('visited_at')->get(),
            'programs' => Program::orderBy('name')->get(),
            'counsellors' => User::role(['admission_counsellor', 'admission_officer', 'admission_manager', 'admin'])->orderBy('name')->get(),
            'report' => $service->report(),
        ]);
    }

    public function store(Request $request, AdmissionWalkInService $service)
    {
        $service->record($request->validate([
            'visitor_name' => ['required', 'string', 'max:120'],
            'visitor_phone' => ['nullable', 'string', 'max:40'],
            'visitor_email' => ['nullable', 'email', 'max:160'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'purpose' => ['required', 'string', 'max:120'],
            'assigned_counsellor_id' => ['nullable', 'exists:users,id'],
            'visited_at' => ['nullable', 'date'],
            'next_followup_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]), $request->user());

        return back()->with('success', 'Walk-in visit recorded.');
    }

    public function convert(AdmissionWalkIn $walkIn, Request $request, AdmissionWalkInService $service)
    {
        $lead = $service->convertToLead($walkIn, $request->user());

        return redirect()->route('admission.leads.show', $lead)->with('success', 'Walk-in converted to lead.');
    }
}
