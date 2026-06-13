<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

class TransportController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        if (!$student) {
            return redirect()->route('student.dashboard');
        }

        $assignment = $student->activeTransportAssignment()
            ->with(['route', 'stop', 'vehicle'])
            ->first();

        $history = $student->transportAssignments()
            ->with(['route', 'stop', 'vehicle'])
            ->latest()
            ->limit(10)
            ->get();

        return view('student.transport.index', compact('assignment', 'history'));
    }
}
