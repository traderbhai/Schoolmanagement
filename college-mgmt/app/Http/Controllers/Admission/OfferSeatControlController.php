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
use App\Services\AdmissionSensitiveAuditService;
use App\Services\AdmissionWaitlistService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferSeatControlController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        $applicantColumns = [
            'a.application_number as applicant_application_number',
            'u.name as applicant_name',
            'u.email as applicant_email',
        ];
        $canManageSeatControl = $this->canManageSeatControl($request);
        $visibleApplicantIds = $this->visibleApplicantIds($request);
        $visibleProgramIds = $this->visibleProgramIds($request);

        return view('admission.v0038.offer-seat-control', [
            'canManageSeatControl' => $canManageSeatControl,
            'programs' => Program::when($visibleProgramIds !== null, fn ($q) => $q->whereIn('id', $visibleProgramIds))->orderBy('name')->get(),
            'batches' => Batch::when($visibleProgramIds !== null, fn ($q) => $q->whereIn('program_id', $visibleProgramIds))->orderByDesc('start_date')->get(),
            'selectedApplicants' => Applicant::with(['user', 'program'])
                ->when($visibleApplicantIds !== null, fn ($q) => $q->whereIn('id', $visibleApplicantIds))
                ->whereIn('status', ['selected', 'shortlisted', 'submitted', 'under_review'])
                ->latest()
                ->limit(75)
                ->get(),
            'rounds' => DB::table('admission_offer_rounds')
                ->when($visibleProgramIds !== null, fn ($q) => $q->whereIn('program_id', $visibleProgramIds))
                ->latest()
                ->paginate(10),
            'waitlist' => DB::table('admission_waitlist_entries as w')
                ->leftJoin('applicants as a', 'a.id', '=', 'w.applicant_id')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->select(array_merge(['w.*'], $applicantColumns))
                ->when($visibleApplicantIds !== null, fn ($q) => $q->whereIn('w.applicant_id', $visibleApplicantIds))
                ->orderBy('w.rank')
                ->limit(30)
                ->get(),
            'holds' => DB::table('admission_seat_holds as h')
                ->leftJoin('applicants as a', 'a.id', '=', 'h.applicant_id')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->select(array_merge(['h.*'], $applicantColumns))
                ->when($visibleApplicantIds !== null, fn ($q) => $q->whereIn('h.applicant_id', $visibleApplicantIds))
                ->latest('h.created_at')
                ->limit(30)
                ->get(),
            'deferrals' => DB::table('admission_deferrals as d')
                ->leftJoin('applicants as a', 'a.id', '=', 'd.applicant_id')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->leftJoin('batches as b', 'b.id', '=', 'd.to_batch_id')
                ->select(array_merge(['d.*'], $applicantColumns, ['b.name as target_batch_name']))
                ->when($visibleApplicantIds !== null, fn ($q) => $q->whereIn('d.applicant_id', $visibleApplicantIds))
                ->latest('d.created_at')
                ->limit(20)
                ->get(),
            'joiningTasks' => DB::table('admission_joining_kit_tasks as j')
                ->leftJoin('applicants as a', 'a.id', '=', 'j.applicant_id')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->select(array_merge(['j.*'], $applicantColumns))
                ->when($visibleApplicantIds !== null, fn ($q) => $q->whereIn('j.applicant_id', $visibleApplicantIds))
                ->latest('j.created_at')
                ->limit(30)
                ->get(),
        ]);
    }

    public function createRound(Request $request, AdmissionOfferRoundService $service)
    {
        $this->authorizeSeatControlWrite($request);
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
        $this->authorizeSeatControlWrite($request);
        $count = $service->publish($roundId, $request->user());
        return back()->with('success', "Offer round published and {$count} seat hold(s) created.");
    }

    public function addWaitlist(Request $request, AdmissionWaitlistService $service)
    {
        $this->authorizeSeatControlWrite($request);
        $data = $request->validate(['applicant_id' => ['required', 'exists:applicants,id'], 'rank' => ['required', 'integer'], 'offer_round_id' => ['nullable', 'integer']]);
        $applicant = Applicant::findOrFail($data['applicant_id']);
        $this->guardApplicantScope($request, $applicant);
        $service->add($applicant, $data);
        return back()->with('success', 'Applicant added to waitlist.');
    }

    public function releaseSeat(int $holdId, Request $request, AdmissionSeatControlService $service, AdmissionSensitiveAuditService $audit)
    {
        $this->authorizeSeatControlWrite($request);
        $data = $request->validate(['reason' => ['required', 'string']]);
        $before = (array) DB::table('admission_seat_holds')->where('id', $holdId)->first();
        abort_if(empty($before), 404);
        $this->guardApplicantScope($request, Applicant::findOrFail($before['applicant_id']));
        $service->release($holdId, $data['reason'], $request->user());
        $after = (array) DB::table('admission_seat_holds')->where('id', $holdId)->first();
        $audit->record('seat_release', null, $request->user(), $data['reason'], $before, $after);
        return back()->with('success', 'Seat released and waitlist promotion checked.');
    }

    public function requestDeferral(Request $request, AdmissionDeferralService $service)
    {
        $this->authorizeSeatControlWrite($request);
        $data = $request->validate(['applicant_id' => ['required', 'exists:applicants,id'], 'to_batch_id' => ['required', 'exists:batches,id'], 'reason' => ['required', 'string']]);
        $applicant = Applicant::findOrFail($data['applicant_id']);
        $this->guardApplicantScope($request, $applicant);
        $service->request($applicant, (int) $data['to_batch_id'], $data['reason']);
        return back()->with('success', 'Deferral request recorded.');
    }

    public function approveDeferral(int $deferralId, Request $request, AdmissionDeferralService $service, AdmissionSensitiveAuditService $audit)
    {
        $this->authorizeSeatControlWrite($request);
        $before = (array) DB::table('admission_deferrals')->where('id', $deferralId)->first();
        abort_if(empty($before), 404);
        $this->guardApplicantScope($request, Applicant::findOrFail($before['applicant_id']));
        $service->approve($deferralId, $request->user(), $request->input('carry_forward_notes'));
        $after = (array) DB::table('admission_deferrals')->where('id', $deferralId)->first();
        $audit->record('deferral_approval', null, $request->user(), $request->input('carry_forward_notes'), $before, $after);
        return back()->with('success', 'Deferral approved.');
    }

    public function ensureJoiningKit(int $applicantId, Request $request, AdmissionJoiningKitService $service)
    {
        $this->authorizeSeatControlWrite($request);
        $applicant = Applicant::findOrFail($applicantId);
        $this->guardApplicantScope($request, $applicant);
        $service->ensure($applicant, $request->user());
        return back()->with('success', 'Joining kit checklist prepared.');
    }

    private function canManageSeatControl(Request $request): bool
    {
        return $this->hierarchy->canApproveAdmission($request->user());
    }

    private function authorizeSeatControlWrite(Request $request): void
    {
        abort_unless($this->canManageSeatControl($request), 403);
    }

    private function guardApplicantScope(Request $request, Applicant $applicant): void
    {
        abort_unless($this->hierarchy->canViewAssignedUser($request->user(), 'ADM', $applicant->assigned_to, false), 403);
    }

    private function visibleApplicantIds(Request $request)
    {
        if ($this->hierarchy->canSeeAll($request->user(), 'ADM')) {
            return null;
        }

        $query = Applicant::query();
        $this->hierarchy->applyApplicantVisibility($query, $request->user(), 'ADM');

        return $query->pluck('id');
    }

    private function visibleProgramIds(Request $request)
    {
        if ($this->hierarchy->canSeeAll($request->user(), 'ADM')) {
            return null;
        }

        $query = Applicant::query();
        $this->hierarchy->applyApplicantVisibility($query, $request->user(), 'ADM');

        return $query->pluck('program_id')->filter()->unique()->values();
    }
}
