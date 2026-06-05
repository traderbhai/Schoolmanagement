<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    private function getTeacher()
    {
        return auth()->user()->teacher;
    }

    public function index()
    {
        $teacher = $this->getTeacher();
        $leaves = $teacher->leaveApplications()->latest()->paginate(15);
        return view('teacher.leaves.index', compact('leaves'));
    }

    public function create()
    {
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

        $from = \Carbon\Carbon::parse($request->from_date);
        $to   = \Carbon\Carbon::parse($request->to_date);
        $days = $from->diffInDays($to) + 1;

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

        if ($leave->teacher_id !== $teacher->id || $leave->status !== 'pending') {
            return back()->with('error', 'Cannot cancel this leave application.');
        }

        $leave->delete();
        return back()->with('success', 'Leave application cancelled.');
    }
}
