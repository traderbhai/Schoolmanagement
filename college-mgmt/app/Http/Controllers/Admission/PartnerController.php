<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionPartner;
use App\Models\Program;
use App\Services\AdmissionPartnerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'email' => ['nullable', 'required_without:phone', 'email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:50'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'partner_reference' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:urgent,high,normal,low'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Partner lead submitted.');
    }

    public function portalDashboard(Request $request, AdmissionPartnerService $service)
    {
        $partner = $this->partnerFor($request);
        $summary = $service->dashboard($partner);
        $statusCounts = $partner->leads()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admission.partner.dashboard', [
            'partner' => $partner,
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'latestLeads' => $partner->leads()->latest()->limit(8)->get(),
            'programs' => $this->allowedPrograms($partner),
        ]);
    }

    public function portalLeads(Request $request)
    {
        $partner = $this->partnerFor($request);
        $query = $partner->leads()
            ->with('program')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('email', 'like', '%'.$request->q.'%')
                    ->orWhere('phone', 'like', '%'.$request->q.'%')
                    ->orWhere('partner_reference', 'like', '%'.$request->q.'%');
            }))
            ->latest();

        return view('admission.partner.leads', [
            'partner' => $partner,
            'leads' => $query->paginate(15)->withQueryString(),
            'status' => $request->status,
            'q' => $request->q,
        ]);
    }

    public function portalSubmitLead(Request $request, AdmissionPartnerService $service)
    {
        $partner = $this->partnerFor($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:50'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'partner_reference' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:urgent,high,normal,low'],
            'notes' => ['nullable', 'string'],
        ]);

        $lead = $service->submitLead($partner, $data);

        return redirect()
            ->route('admission.partner-portal.leads', ['q' => $lead->email ?: $lead->phone])
            ->with('success', 'Lead submitted to the admission team.');
    }

    private function partnerFor(Request $request): AdmissionPartner
    {
        $partner = AdmissionPartner::where('contact_user_id', $request->user()->id)
            ->orWhere('contact_email', $request->user()->email)
            ->first();

        abort_unless($partner, 403, 'You are not linked to an admission partner account.');

        return $partner;
    }

    private function allowedPrograms(AdmissionPartner $partner)
    {
        $ids = collect($partner->allowed_program_ids ?? [])->filter()->map(fn ($id) => (int) $id);

        return Program::query()
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }
}
