<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationExecution;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionAutomationService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        return view('admission.v003.automations', [
            'canManageAutomations' => $this->canManageAutomations($request),
            'automations' => AdmissionAutomation::orderBy('priority')->get(),
            'executions' => $this->automationExecutionQuery($request)->with('automation')->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAutomationWrite($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'max:60'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'conditions_json' => ['nullable', 'json'],
            'actions_json' => ['required', 'json'],
        ]);

        AdmissionAutomation::create([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'priority' => $data['priority'] ?? 100,
            'conditions' => json_decode($data['conditions_json'] ?: '{}', true),
            'actions' => json_decode($data['actions_json'], true),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Automation saved.');
    }

    public function run(Request $request, AdmissionAutomationService $service)
    {
        $this->authorizeAutomationWrite($request);

        $data = $request->validate([
            'trigger' => ['required', 'string', 'max:60'],
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
        ]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $this->guardSubjectScope($request, $subject);
        $executions = $service->run($data['trigger'], $subject, $request->user());

        return back()->with('success', $executions->count() . ' automation(s) evaluated.');
    }

    private function canManageAutomations(Request $request): bool
    {
        return $this->hierarchy->canApproveAdmission($request->user());
    }

    private function authorizeAutomationWrite(Request $request): void
    {
        abort_unless($this->canManageAutomations($request), 403);
    }

    private function guardSubjectScope(Request $request, Lead|Applicant $subject): void
    {
        abort_unless($this->hierarchy->canViewAssignedUser($request->user(), 'ADM', $subject->assigned_to, $subject instanceof Lead), 403);
    }

    private function automationExecutionQuery(Request $request)
    {
        $query = AdmissionAutomationExecution::query();

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
