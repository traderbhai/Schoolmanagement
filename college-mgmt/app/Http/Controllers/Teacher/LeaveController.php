<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaveController extends Controller
{
    private function getTeacher()
    {
        return auth()->user()->teacher;
    }

    public function index()
    {
        $teacher = $this->getTeacher();
        if (! $teacher) {
            $leaves = new LengthAwarePaginator([], 0, 15);
            $canApplyForLeave = false;
            $profileMissing = true;

            return view('teacher.leaves.index', compact('leaves', 'canApplyForLeave', 'profileMissing'));
        }

        $leaves = $teacher->leaveApplications()->latest()->paginate(15);
        $canApplyForLeave = $teacher->status === 'active';
        $profileMissing = false;

        return view('teacher.leaves.index', compact('leaves', 'canApplyForLeave', 'profileMissing'));
    }

    public function create()
    {
        $teacher = $this->getTeacher();
        if (! $teacher) {
            return redirect()
                ->route('teacher.leaves.index')
                ->with('error', 'Your teacher profile is not linked yet. Contact administration before submitting leave.');
        }

        if ($teacher->status !== 'active') {
            return redirect()
                ->route('teacher.leaves.index')
                ->with('error', 'Leave applications can be submitted only by active teachers.');
        }

        return view('teacher.leaves.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|in:casual,medical,earned,duty,maternity,paternity',
            'from_date'  => 'required|date|after_or_equal:today',
            'to_date'    => 'required|date|after_or_equal:from_date',
            'reason'     => 'required|string|max:1000',
        ]);

        $teacher = $this->getTeacher();
        if (! $teacher) {
            return redirect()
                ->route('teacher.leaves.index')
                ->with('error', 'Your teacher profile is not linked yet. Contact administration before submitting leave.');
        }

        if ($teacher->status !== 'active') {
            return redirect()
                ->route('teacher.leaves.index')
                ->with('error', 'Leave applications can be submitted only by active teachers.');
        }

        $from = Carbon::parse($request->from_date)->startOfDay();
        $to   = Carbon::parse($request->to_date)->startOfDay();
        $days = $from->diffInDays($to) + 1;

        $overlap = $teacher->leaveApplications()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString())
            ->first();

        if ($overlap) {
            return back()
                ->withInput()
                ->withErrors([
                    'from_date' => 'This leave overlaps an existing open leave request from '
                        . $overlap->from_date->format('d M Y') . ' to '
                        . $overlap->to_date->format('d M Y') . '.',
                ]);
        }

        $teacher->leaveApplications()->create([
            'leave_type' => $request->leave_type,
            'from_date'  => $request->from_date,
            'to_date'    => $request->to_date,
            'days'       => $days,
            'reason'     => $request->reason,
            'status'     => 'pending',
        ]);

        return redirect()->route('teacher.leaves.index')->with('success', 'Leave application submitted successfully.');
    }

    public function destroy(LeaveApplication $leave)
    {
        $teacher = $this->getTeacher();

        if (! $teacher || $teacher->status !== 'active' || $leave->teacher_id !== $teacher->id || $leave->status !== 'pending') {
            return back()->with('error', 'Cannot cancel this leave application.');
        }

        $leave->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'admin_remarks' => 'Cancelled by teacher before review.',
        ]);

        return back()->with('success', 'Leave application cancelled.');
    }
}
