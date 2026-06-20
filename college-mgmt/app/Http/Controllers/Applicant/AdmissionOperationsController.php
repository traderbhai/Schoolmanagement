<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\AdmissionAssessmentSlotService;
use App\Services\AdmissionConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionOperationsController extends Controller
{
    public function index(Request $request)
    {
        $applicant = $this->applicant($request);
        $canRequestAssessmentChanges = ! $this->isFinalAdmissionState($applicant);

        return view('applicant.admission-operations', [
            'applicant' => $applicant,
            'canRequestAssessmentChanges' => $canRequestAssessmentChanges,
            'slots' => DB::table('admission_assessment_slot_assignments')
                ->join('admission_assessment_slots', 'admission_assessment_slots.id', '=', 'admission_assessment_slot_assignments.slot_id')
                ->where('admission_assessment_slot_assignments.applicant_id', $applicant->id)
                ->select('admission_assessment_slot_assignments.*', 'admission_assessment_slots.slot_code', 'admission_assessment_slots.starts_at', 'admission_assessment_slots.venue', 'admission_assessment_slots.online_link')
                ->latest('admission_assessment_slot_assignments.created_at')
                ->get(),
            'availableSlots' => $this->availableSlotsFor($applicant),
            'reschedules' => DB::table('admission_assessment_reschedule_requests')->where('applicant_id', $applicant->id)->latest()->get(),
            'submissions' => DB::table('admission_assessment_submissions')->where('applicant_id', $applicant->id)->latest()->get(),
            'consents' => DB::table('admission_consent_records')->where('subject_type', Applicant::class)->where('subject_id', $applicant->id)->get()->keyBy('channel'),
            'waitlist' => DB::table('admission_waitlist_entries')->where('applicant_id', $applicant->id)->latest()->get(),
            'seatHolds' => DB::table('admission_seat_holds')->where('applicant_id', $applicant->id)->latest()->get(),
            'joiningTasks' => DB::table('admission_joining_kit_tasks')->where('applicant_id', $applicant->id)->orderBy('due_at')->get(),
            'deferrals' => DB::table('admission_deferrals')->where('applicant_id', $applicant->id)->latest()->get(),
            'handoff' => DB::table('admission_handoff_records')->where('applicant_id', $applicant->id)->first(),
        ]);
    }

    public function requestReschedule(Request $request, AdmissionAssessmentSlotService $service)
    {
        $applicant = $this->applicant($request);

        if ($this->isFinalAdmissionState($applicant)) {
            return back()->with('error', 'Assessment reschedule requests are closed because this application is already in a final admission state.');
        }

        $data = $request->validate([
            'slot_assignment_id' => ['required', 'integer'],
            'requested_slot_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $assignment = DB::table('admission_assessment_slot_assignments')
            ->where('id', $data['slot_assignment_id'])
            ->where('applicant_id', $applicant->id)
            ->firstOrFail();

        $service->requestReschedule($assignment->id, $applicant, $data['reason'], $data['requested_slot_id'] ?? null);

        return back()->with('success', 'Assessment reschedule request submitted.');
    }

    public function consent(Request $request, AdmissionConsentService $service)
    {
        $applicant = $this->applicant($request);
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms,whatsapp,call'],
            'status' => ['required', 'in:opt_in,opt_out'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $service->set($applicant, $data['channel'], $data['status'], $request->user(), $data['reason'] ?? 'Applicant self-service', 'applicant_portal');

        return back()->with('success', 'Consent preference updated.');
    }

    private function applicant(Request $request): Applicant
    {
        return Applicant::where('user_id', $request->user()->id)->with(['user', 'program', 'batch'])->firstOrFail();
    }

    private function availableSlotsFor(Applicant $applicant)
    {
        return DB::table('admission_assessment_slots')
            ->leftJoin('selection_sessions', 'selection_sessions.id', '=', 'admission_assessment_slots.selection_session_id')
            ->where('admission_assessment_slots.status', 'open')
            ->where(function ($query) use ($applicant) {
                $query->whereNull('selection_sessions.id')
                    ->orWhere(function ($scoped) use ($applicant) {
                        $scoped->where('selection_sessions.program_id', $applicant->program_id)
                            ->where(function ($batch) use ($applicant) {
                                $batch->whereNull('selection_sessions.batch_id')
                                    ->orWhere('selection_sessions.batch_id', $applicant->batch_id);
                            });
                    });
            })
            ->select('admission_assessment_slots.*')
            ->orderBy('admission_assessment_slots.starts_at')
            ->limit(30)
            ->get();
    }

    private function isFinalAdmissionState(Applicant $applicant): bool
    {
        return in_array($applicant->status, ['selected', 'rejected', 'withdrawn', 'enrolled'], true);
    }
}
