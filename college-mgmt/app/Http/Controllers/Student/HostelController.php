<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{HostelAllocation, HostelComplaint, OutpassRequest};
use Illuminate\Http\Request;

class HostelController extends Controller
{
    public function outpassIndex()
    {
        $student    = auth()->user()->student;
        $allocation = $student
            ? HostelAllocation::where('student_id', $student->id)->where('status', 'active')->first()
            : null;

        $requests = $student
            ? OutpassRequest::where('student_id', $student->id)->latest()->take(20)->get()
            : collect();

        return view('student.hostel.outpass', compact('allocation', 'requests'));
    }

    public function outpassStore(Request $r)
    {
        $student = auth()->user()->student;

        if (! $student) {
            return back()->withErrors(['error' => 'Student profile not found.']);
        }

        $allocation = HostelAllocation::where('student_id', $student->id)->where('status', 'active')->first();

        if (! $allocation) {
            return back()->withErrors(['error' => 'You must have an active hostel allocation to request an outpass.']);
        }

        $openRequestExists = OutpassRequest::where('student_id', $student->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($openRequestExists) {
            return back()->withErrors(['error' => 'You already have an open outpass request. Track or complete it before submitting another.']);
        }

        $r->validate([
            'reason'          => 'required|string|max:1000',
            'out_datetime'    => 'required|date|after:now',
            'expected_return' => 'required|date|after:out_datetime',
        ]);

        OutpassRequest::create([
            'student_id'          => $student->id,
            'hostel_allocation_id' => $allocation->id,
            'reason'              => $r->reason,
            'out_datetime'        => $r->out_datetime,
            'expected_return'     => $r->expected_return,
            'status'              => 'pending',
        ]);

        return back()->with('success', 'Outpass request submitted successfully.');
    }

    public function complaintsIndex()
    {
        $student = auth()->user()->student;
        abort_unless($student, 403);

        $allocation = HostelAllocation::with('room.block')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        $complaints = HostelComplaint::with(['room.block', 'assignedTo'])
            ->where('student_id', $student->id)
            ->latest()
            ->paginate(15);

        return view('student.hostel.complaints', compact('allocation', 'complaints'));
    }

    public function complaintStore(Request $request)
    {
        $student = auth()->user()->student;
        abort_unless($student, 403);

        $allocation = HostelAllocation::with('room.block')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if (! $allocation) {
            return back()->withErrors(['error' => 'You must have an active hostel allocation to raise a hostel complaint.']);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:20|max:2000',
            'category' => 'required|in:maintenance,hygiene,food,security,ragging,other',
            'priority' => 'required|in:low,medium,high',
        ]);

        HostelComplaint::create([
            'student_id' => $student->id,
            'hostel_room_id' => $allocation->hostel_room_id,
            'hostel_block_id' => $allocation->room?->hostel_block_id,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
        ]);

        return back()->with('success', 'Hostel complaint submitted. The warden team can now track and update it.');
    }
}
