<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{ApprovalWorkflow, Applicant, Program, Student, Teacher, Subject, LeaveRequest};
use Illuminate\Http\Request;

class HodController extends Controller
{
    public function dashboard()
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $totalStudents = Student::count();
        $pendingApprovals = ApprovalWorkflow::where('approver_role', 'hod')
            ->where('status', 'pending')
            ->count();
        $pendingLeaves = 0;
        if (class_exists(LeaveRequest::class)) {
            $pendingLeaves = LeaveRequest::where('status', 'pending')->count();
        }

        return view('departmental.hod.dashboard', compact(
            'programs', 'totalStudents', 'pendingApprovals', 'pendingLeaves'
        ));
    }

    public function approvals(Request $request)
    {
        $query = ApprovalWorkflow::where('approver_role', 'hod')
            ->where('status', 'pending')
            ->with(['approvable', 'approver'])
            ->latest();

        if ($request->filled('program_id')) {
            $query->whereHasMorph('approvable', [Applicant::class], function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        $approvals = $query->paginate(20)->withQueryString();

        // Eager-load nested relations on the already-loaded approvable instances
        $approvals->getCollection()->each(function ($approval) {
            if ($approval->approvable instanceof Applicant) {
                $approval->approvable->load(['user', 'program', 'batch']);
            }
        });

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
