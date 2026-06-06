<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{ApprovalWorkflow, Applicant, Program};
use Illuminate\Http\Request;

class HodController extends Controller
{
    public function approvals(Request $request)
    {
        // Get HOD's primary department (via role assignment or relationship)
        $hodDepartment = auth()->user()->departments()->first();
        if (!$hodDepartment) {
            return back()->with('error', 'HOD department not assigned.');
        }

        // Get pending approvals for applicants in this department's programs
        $query = ApprovalWorkflow::where('approver_role', 'hod')
            ->where('status', 'pending')
            ->with(['approvable' => function ($q) {
                $q->whereType(Applicant::class)
                  ->with(['user', 'program', 'batch']);
            }, 'approver'])
            ->latest();

        // Filter by program if selected
        if ($request->filled('program_id')) {
            $query->whereHas('approvable', function ($q) use ($request) {
                $q->whereType(Applicant::class)
                  ->where('program_id', $request->program_id);
            });
        }

        $approvals = $query->paginate(20)->withQueryString();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('departmental.hod.approvals.index', compact('approvals', 'programs'));
    }

    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $approval->update([
            'status'      => 'approved',
            'approver_id' => auth()->id(),
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        $applicantName = $approval->approvable instanceof Applicant
            ? ($approval->approvable->user->name ?? 'applicant')
            : 'applicant';

        return back()->with('success', "Approval granted for {$applicantName}.");
    }

    public function reject(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $approval->update([
            'status'      => 'rejected',
            'approver_id' => auth()->id(),
            'remarks'     => $request->rejection_reason,
            'approved_at' => now(),
        ]);

        $applicantName = $approval->approvable instanceof Applicant
            ? ($approval->approvable->user->name ?? 'applicant')
            : 'applicant';

        return back()->with('error', "Approval rejected for {$applicantName}.");
    }
}
