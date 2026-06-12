<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{LeaveApplication, Teacher, AuditLog};
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaveApplication::with('teacher.user')->latest();

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
        $leave->load('teacher.user', 'approver');
        return view('admin.leaves.show', compact('leave'));
    }

    public function approve(Request $request, LeaveApplication $leave)
    {
        $request->validate(['admin_remarks' => 'nullable|string|max:1000']);

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
        $request->validate(['admin_remarks' => 'nullable|string|max:1000']);

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
        $leave->delete();
        return redirect()->route('admin.leaves.index')->with('success', 'Leave application deleted.');
    }
}
