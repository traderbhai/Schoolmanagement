<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Services\AdmissionDeferralService;
use App\Services\AdmissionJoiningKitService;
use App\Services\AdmissionOfferRoundService;
use App\Services\AdmissionSeatControlService;
use App\Services\AdmissionWaitlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferSeatControlController extends Controller
{
    public function index()
    {
        return view('admission.v0038.offer-seat-control', [
            'programs' => Program::orderBy('name')->get(),
            'batches' => Batch::orderByDesc('start_date')->get(),
            'selectedApplicants' => Applicant::with('user')->whereIn('status', ['selected', 'shortlisted'])->limit(30)->get(),
            'rounds' => DB::table('admission_offer_rounds')->latest()->paginate(10),
            'waitlist' => DB::table('admission_waitlist_entries')->orderBy('rank')->limit(30)->get(),
            'holds' => DB::table('admission_seat_holds')->latest()->limit(30)->get(),
            'deferrals' => DB::table('admission_deferrals')->latest()->limit(20)->get(),
            'joiningTasks' => DB::table('admission_joining_kit_tasks')->latest()->limit(30)->get(),
        ]);
    }

    public function createRound(Request $request, AdmissionOfferRoundService $service)
    {
        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'batch_id' => ['required', 'exists:batches,id'],
            'round_number' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string'],
            'offer_valid_until' => ['required', 'date'],
        ]);
        $service->create($data + ['status' => 'draft']);
        return back()->with('success', 'Offer round created.');
    }

    public function publishRound(int $roundId, Request $request, AdmissionOfferRoundService $service)
    {
        $count = $service->publish($roundId, $request->user());
        return back()->with('success', "Offer round published and {$count} seat hold(s) created.");
    }

    public function addWaitlist(Request $request, AdmissionWaitlistService $service)
    {
        $data = $request->validate(['applicant_id' => ['required', 'exists:applicants,id'], 'rank' => ['required', 'integer'], 'offer_round_id' => ['nullable', 'integer']]);
        $service->add(Applicant::findOrFail($data['applicant_id']), $data);
        return back()->with('success', 'Applicant added to waitlist.');
    }

    public function releaseSeat(int $holdId, Request $request, AdmissionSeatControlService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $service->release($holdId, $data['reason'], $request->user());
        return back()->with('success', 'Seat released and waitlist promotion checked.');
    }

    public function requestDeferral(Request $request, AdmissionDeferralService $service)
    {
        $data = $request->validate(['applicant_id' => ['required', 'exists:applicants,id'], 'to_batch_id' => ['required', 'exists:batches,id'], 'reason' => ['required', 'string']]);
        $service->request(Applicant::findOrFail($data['applicant_id']), (int) $data['to_batch_id'], $data['reason']);
        return back()->with('success', 'Deferral request recorded.');
    }

    public function approveDeferral(int $deferralId, Request $request, AdmissionDeferralService $service)
    {
        $service->approve($deferralId, $request->user(), $request->input('carry_forward_notes'));
        return back()->with('success', 'Deferral approved.');
    }

    public function ensureJoiningKit(int $applicantId, Request $request, AdmissionJoiningKitService $service)
    {
        $service->ensure(Applicant::findOrFail($applicantId), $request->user());
        return back()->with('success', 'Joining kit checklist prepared.');
    }
}
