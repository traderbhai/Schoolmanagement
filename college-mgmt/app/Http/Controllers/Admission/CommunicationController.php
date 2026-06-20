<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionCommunicationService;
use App\Services\AdmissionSafeCommunicationService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        return view('admission.v003.communication', [
            'canManageCommunication' => $this->canManageCommunication($request),
            'templates' => AdmissionCommunicationTemplate::latest()->get(),
            'logs' => $this->communicationLogQuery($request)->with('template')->latest()->limit(50)->get(),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $this->authorizeCommunicationWrite($request);

        AdmissionCommunicationTemplate::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:email,internal,sms,whatsapp'],
            'purpose' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Communication template saved.');
    }

    public function send(Request $request, AdmissionSafeCommunicationService $service)
    {
        $data = $request->validate([
            'template_id' => ['required', 'exists:admission_communication_templates,id'],
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
        ]);
        $subject = $data['subject_type'] === 'lead'
            ? Lead::findOrFail($data['subject_id'])
            : Applicant::findOrFail($data['subject_id']);
        $this->guardSubjectScope($request, $subject);

        $template = AdmissionCommunicationTemplate::findOrFail($data['template_id']);
        $log = $service->queue($subject, $template, $request->user());
        if (isset($log->blocked_by_rule)) {
            return back()->with('warning', "Communication blocked by safety rule: {$log->reason}.");
        }

        return back()->with('success', "Communication queued as #{$log->id}.");
    }

    public function dispatch(Request $request, AdmissionCommunicationService $service)
    {
        $this->authorizeCommunicationWrite($request);
        $count = $service->dispatchQueued();

        return back()->with('success', "{$count} queued communications marked sent.");
    }

    private function canManageCommunication(Request $request): bool
    {
        return $this->hierarchy->canApproveAdmission($request->user());
    }

    private function authorizeCommunicationWrite(Request $request): void
    {
        abort_unless($this->canManageCommunication($request), 403);
    }

    private function guardSubjectScope(Request $request, Lead|Applicant $subject): void
    {
        $assignedTo = $subject instanceof Lead ? $subject->assigned_to : $subject->assigned_to;
        abort_unless($this->hierarchy->canViewAssignedUser($request->user(), 'ADM', $assignedTo, $subject instanceof Lead), 403);
    }

    private function communicationLogQuery(Request $request)
    {
        $query = AdmissionCommunicationLog::query();

        if ($this->hierarchy->canSeeAll($request->user(), 'ADM')) {
            return $query;
        }

        [$leadIds, $applicantIds] = $this->visibleSubjectIds($request);

        return $query->where(function ($scope) use ($leadIds, $applicantIds) {
            $scope->where(function ($leadScope) use ($leadIds) {
                $leadScope->where('subject_type', Lead::class)->whereIn('subject_id', $leadIds);
            })->orWhere(function ($applicantScope) use ($applicantIds) {
                $applicantScope->where('subject_type', Applicant::class)->whereIn('subject_id', $applicantIds);
            });
        });
    }

    private function visibleSubjectIds(Request $request): array
    {
        $leadQuery = Lead::query();
        $this->hierarchy->applyLeadVisibility($leadQuery, $request->user(), 'ADM');

        $applicantQuery = Applicant::query();
        $this->hierarchy->applyApplicantVisibility($applicantQuery, $request->user(), 'ADM');

        return [$leadQuery->pluck('id'), $applicantQuery->pluck('id')];
    }
}
