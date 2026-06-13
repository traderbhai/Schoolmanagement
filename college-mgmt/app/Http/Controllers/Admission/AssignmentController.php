<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function assignApplicant(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'sla_due_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$this->hierarchy->canAssignTo($request->user(), (int) $validated['assigned_to'], 'ADM')) {
            abort(403, 'You cannot assign applicants outside your admission hierarchy scope.');
        }

        $applicant->update(array_merge($validated, ['assigned_at' => now()]));

        $this->hierarchy->recordActivity(
            'ADM',
            $request->user(),
            'applicant_assigned',
            'Assigned applicant ' . $applicant->application_number . '.',
            $applicant,
            \App\Models\User::find((int) $validated['assigned_to']),
            ['priority' => $validated['priority'] ?? null]
        );

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

        if (!$this->hierarchy->canAssignTo($request->user(), (int) $validated['assigned_to'], 'ADM')) {
            abort(403, 'You cannot assign leads outside your admission hierarchy scope.');
        }

        $lead->update(array_merge($validated, ['assigned_at' => now()]));

        $this->hierarchy->recordActivity(
            'ADM',
            $request->user(),
            'lead_assigned',
            'Assigned lead ' . $lead->name . '.',
            $lead,
            \App\Models\User::find((int) $validated['assigned_to']),
            ['priority' => $validated['priority'] ?? null]
        );

        return back()->with('success', 'Lead assignment updated.');
    }
}
