<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionPartner;
use App\Models\Program;
use App\Services\AdmissionPartnerService;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(AdmissionPartnerService $service)
    {
        return view('admission.v003.partners', [
            'partners' => AdmissionPartner::latest()->get(),
            'programs' => Program::orderBy('name')->get(),
            'service' => $service,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'allowed_program_ids' => ['nullable', 'array'],
        ]);
        AdmissionPartner::create($data + ['status' => 'pending']);

        return back()->with('success', 'Partner created for approval.');
    }

    public function approve(AdmissionPartner $partner, Request $request, AdmissionPartnerService $service)
    {
        $service->approve($partner, $request->user());

        return back()->with('success', 'Partner approved.');
    }

    public function submitLead(AdmissionPartner $partner, Request $request, AdmissionPartnerService $service)
    {
        $service->submitLead($partner, $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'partner_reference' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:urgent,high,normal,low'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Partner lead submitted.');
    }
}
