<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\AccessControl;
use App\Models\{LeaveApplication, Teacher, AuditLog};
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeGlobalLeaves($request);

        $query = LeaveApplication::with(['teacher.user', 'student.user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $leaves = $query->paginate(20)->withQueryString();

        $counts = [
            'pending'  => LeaveApplication::where('status', 'pending')->count(),
            'approved' => LeaveApplication::where('status', 'approved')->count(),
            'rejected' => LeaveApplication::where('status', 'rejected')->count(),
        ];

        $teachers = Teacher::with('user')->orderBy('id')->get();

        return view('admin.leaves.index', compact('leaves', 'counts', 'teachers'));
    }

    public function show(LeaveApplication $leave)
    {
        $this->authorizeGlobalLeaves(request());

        $leave->load('teacher.user', 'student.user', 'approver');
        return view('admin.leaves.show', compact('leave'));
    }

    public function approve(Request $request, LeaveApplication $leave)
    {
        $this->authorizeGlobalLeaves($request);

        $request->validate(['admin_remarks' => 'nullable|string|max:1000']);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be approved.');
        }

        $leave->update([
            'status'        => 'approved',
            'admin_remarks' => $request->admin_remarks,
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
        ]);

        AuditLog::log('leave_approved', $leave, ['teacher' => $leave->teacher?->user?->name, 'remarks' => $request->admin_remarks]);
        return back()->with('success', 'Leave approved successfully.');
    }

    public function reject(Request $request, LeaveApplication $leave)
    {
        $this->authorizeGlobalLeaves($request);

        $request->validate(['admin_remarks' => 'required|string|max:1000']);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be rejected.');
        }

        $leave->update([
            'status'        => 'rejected',
            'admin_remarks' => $request->admin_remarks,
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
        ]);

        AuditLog::log('leave_rejected', $leave, ['teacher' => $leave->teacher?->user?->name, 'remarks' => $request->admin_remarks]);
        return back()->with('success', 'Leave rejected.');
    }

    public function destroy(LeaveApplication $leave)
    {
        $this->authorizeGlobalLeaves(request());

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Reviewed leave history cannot be deleted.');
        }

        $leave->update([
            'status' => 'rejected',
            'admin_remarks' => 'Cancelled by admin before review.',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLog::log('leave_cancelled', $leave, ['teacher' => $leave->teacher?->user?->name, 'remarks' => 'Cancelled by admin before review.']);
        return redirect()->route('admin.leaves.index')->with('success', 'Leave application cancelled and retained for audit.');
    }

    private function authorizeGlobalLeaves(Request $request): void
    {
        abort_unless(AccessControl::canManageGlobalLeaves($request->user()), 403);
    }
}
