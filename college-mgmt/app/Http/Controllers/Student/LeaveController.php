<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    private function getStudent()
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        return $student;
    }

    public function index()
    {
        $student = $this->getStudent();
        $leaves = LeaveApplication::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('student.leave.index', compact('leaves'));
    }

    public function create()
    {
        return view('student.leave.create');
    }

    public function store(Request $request)
    {
        $student = $this->getStudent();

        $data = $request->validate([
            'from_date'   => 'required|date|after_or_equal:today',
            'to_date'     => 'required|date|after_or_equal:from_date',
            'reason'      => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'attachment'  => 'nullable|file|max:5120',
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store("leave/{$student->id}", 'local');
        }

        LeaveApplication::create([
            'student_id'     => $student->id,
            'from_date'      => $data['from_date'],
            'to_date'        => $data['to_date'],
            'reason'         => $data['reason'],
            'description'    => $data['description'] ?? null,
            'attachment_path' => $path,
        ]);

        return redirect()->route('student.leave.index')
            ->with('success', 'Leave application submitted successfully.');
    }
}
