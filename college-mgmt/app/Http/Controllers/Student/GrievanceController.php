<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentGrievance;
use Illuminate\Http\Request;

class GrievanceController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        if (!$student) abort(403);

        $grievances = StudentGrievance::where('student_id', $student->id)
            ->latest()
            ->paginate(15);

        return view('student.grievances.index', compact('grievances'));
    }

    public function create()
    {
        $categories = ['academic', 'fee', 'hostel', 'examination', 'facility', 'other'];
        return view('student.grievances.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $student = auth()->user()->student;
        if (!$student) abort(403);

        $data = $request->validate([
            'category'    => 'required|in:academic,fee,hostel,examination,facility,other',
            'subject'     => 'required|string|max:255',
            'description' => 'required|string|max:3000',
            'priority'    => 'required|in:low,normal,high,urgent',
        ]);

        $data['student_id'] = $student->id;

        StudentGrievance::create($data);

        return redirect()->route('student.grievances.index')
            ->with('success', 'Your grievance has been submitted. You will be notified once it is reviewed.');
    }

    public function show(StudentGrievance $grievance)
    {
        $student = auth()->user()->student;
        if (!$student || $grievance->student_id !== $student->id) {
            abort(403);
        }

        return view('student.grievances.show', compact('grievance'));
    }
}
