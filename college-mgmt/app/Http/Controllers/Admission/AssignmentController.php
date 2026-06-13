<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use App\Services\AdmissionAssignmentService;
use App\Services\AdmissionWorkflowService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(
        private DepartmentHierarchyService $hierarchy,
        private AdmissionAssignmentService $assignments,
        private AdmissionWorkflowService $workflow,
    ) {}

    public function assignApplicant(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'sla_due_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assignments->assignApplicant($applicant, User::findOrFail($validated['assigned_to']), $request->user(), $validated + [
            'mode' => 'manual',
            'reason' => $request->input('assignment_reason'),
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', 'Applicant assignment updated.');
    }

    public function assignLead(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'sla_due_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
        ]);

        $this->assignments->assignLead($lead, User::findOrFail($validated['assigned_to']), $request->user(), $validated + [
            'mode' => 'manual',
            'reason' => $request->input('assignment_reason'),
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', 'Lead assignment updated.');
    }

    public function delegateLead(Request $request, Lead $lead)
    {
        $validated = $this->delegateValidation($request);
        $this->assignments->delegate($lead, User::findOrFail($validated['assigned_to']), $request->user(), $validated);

        return back()->with('success', 'Lead delegated.');
    }

    public function delegateApplicant(Request $request, Applicant $applicant)
    {
        $validated = $this->delegateValidation($request);
        $this->assignments->delegate($applicant, User::findOrFail($validated['assigned_to']), $request->user(), $validated);

        return back()->with('success', 'Applicant delegated.');
    }

    public function bulkAssignLeads(Request $request)
    {
        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $this->assignments->bulkAssign('lead', $validated['lead_ids'], User::findOrFail($validated['assigned_to']), $request->user(), $validated);

        return back()->with('success', "{$count} lead(s) assigned.");
    }

    public function bulkAssignApplicants(Request $request)
    {
        $validated = $request->validate([
            'applicant_ids' => ['required', 'array', 'min:1'],
            'applicant_ids.*' => ['integer', 'exists:applicants,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $this->assignments->bulkAssign('applicant', $validated['applicant_ids'], User::findOrFail($validated['assigned_to']), $request->user(), $validated);

        return back()->with('success', "{$count} applicant(s) assigned.");
    }

    public function pauseLeadSla(Request $request, Lead $lead)
    {
        $validated = $request->validate(['until' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:255']]);
        $this->workflow->pauseSla($lead, $request->user(), isset($validated['until']) ? \Carbon\Carbon::parse($validated['until']) : null, $validated['reason'] ?? null);

        return back()->with('success', 'Lead SLA paused.');
    }

    public function pauseApplicantSla(Request $request, Applicant $applicant)
    {
        $validated = $request->validate(['until' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:255']]);
        $this->workflow->pauseSla($applicant, $request->user(), isset($validated['until']) ? \Carbon\Carbon::parse($validated['until']) : null, $validated['reason'] ?? null);

        return back()->with('success', 'Applicant SLA paused.');
    }

    private function delegateValidation(Request $request): array
    {
        return $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'sla_due_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
