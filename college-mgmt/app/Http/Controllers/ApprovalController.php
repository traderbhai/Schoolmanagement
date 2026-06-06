<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\AuditLog;
use App\Services\ApprovalChainService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * Unified approval inbox — shows all pending items for the auth user's roles.
     */
    public function inbox(Request $request)
    {
        $user = $request->user();
        $userRoles = $user->getRoleNames()->toArray();

        $query = ApprovalWorkflow::with(['approvable', 'approver'])
            ->whereIn('approver_role', $userRoles)
            ->latest('due_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('workflow_type')) {
            $query->where('workflow_type', $request->workflow_type);
        }

        $approvals = $query->paginate(30)->withQueryString();

        $overdueCount = ApprovalWorkflow::whereIn('approver_role', $userRoles)
            ->overdue()
            ->count();

        $workflowTypes = ApprovalWorkflow::whereIn('approver_role', $userRoles)
            ->select('workflow_type')
            ->distinct()
            ->pluck('workflow_type')
            ->filter();

        $chainDefinitions = ApprovalChainService::getChainDefinitions();

        return view('approvals.inbox', compact(
            'approvals', 'overdueCount', 'workflowTypes', 'chainDefinitions'
        ));
    }

    /**
     * Show chain history for a specific approvable.
     */
    public function chain(ApprovalWorkflow $approval)
    {
        $history = ApprovalChainService::getHistory($approval->approvable);
        $chainDef = ApprovalChainService::getChain($approval->workflow_type ?? 'general');

        return view('approvals.chain', compact('approval', 'history', 'chainDef'));
    }

    /**
     * Approve a step and auto-advance the chain.
     */
    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        $this->authorizeApproval($approval, $request->user());

        $approval->update([
            'status'      => 'approved',
            'approver_id' => $request->user()->id,
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        // Advance chain to next step
        $next = ApprovalChainService::advance($approval);

        $message = $next
            ? 'Approved. Next step sent to ' . ucwords(str_replace('_', ' ', $next->approver_role)) . '.'
            : 'Approved. Workflow chain complete.';

        return back()->with('success', $message);
    }

    /**
     * Reject a step (stops the chain).
     */
    public function reject(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $this->authorizeApproval($approval, $request->user());

        $approval->update([
            'status'      => 'rejected',
            'approver_id' => $request->user()->id,
            'remarks'     => $request->rejection_reason,
            'approved_at' => now(),
        ]);

        return back()->with('error', 'Step rejected. The applicant/requester will be notified.');
    }

    private function authorizeApproval(ApprovalWorkflow $approval, $user): void
    {
        if ($approval->status !== 'pending') {
            abort(422, 'This approval has already been actioned.');
        }

        $userRoles = $user->getRoleNames()->toArray();
        if (!in_array($approval->approver_role, $userRoles) && !$user->hasRole('admin')) {
            abort(403, 'You are not the designated approver for this step.');
        }
    }
}
