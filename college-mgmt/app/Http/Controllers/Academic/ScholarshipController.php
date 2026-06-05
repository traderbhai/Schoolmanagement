<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use App\Models\Student;
use Illuminate\Http\Request;

class ScholarshipController extends Controller
{
    public function index()
    {
        $scholarships = Scholarship::with('student')->paginate(15);
        return view('academic.scholarships.index', compact('scholarships'));
    }

    public function create()
    {
        $students = Student::select('id', 'name')->get();
        return view('academic.scholarships.create', compact('students'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'type' => 'required|in:merit,need_based,category,other',
            'status' => 'required|in:active,inactive,expired',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after:valid_from',
        ]);

        Scholarship::create($validated);

        return redirect()->route('academic.scholarships.index')
            ->with('success', 'Scholarship created successfully');
    }

    public function show(Scholarship $scholarship)
    {
        $scholarship->load('student');
        return view('academic.scholarships.show', compact('scholarship'));
    }

    public function edit(Scholarship $scholarship)
    {
        $students = Student::select('id', 'name')->get();
        return view('academic.scholarships.edit', compact('scholarship', 'students'));
    }

    public function update(Request $request, Scholarship $scholarship)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'type' => 'required|in:merit,need_based,category,other',
            'status' => 'required|in:active,inactive,expired',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after:valid_from',
        ]);

        $scholarship->update($validated);

        return redirect()->route('academic.scholarships.show', $scholarship)
            ->with('success', 'Scholarship updated successfully');
    }

    public function destroy(Scholarship $scholarship)
    {
        $scholarship->delete();
        return redirect()->route('academic.scholarships.index')
            ->with('success', 'Scholarship deleted successfully');
    }
}
