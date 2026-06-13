<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{HostelAllocation, OutpassRequest};
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
}
