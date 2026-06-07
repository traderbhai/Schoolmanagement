<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Internship, Student, Company};
use Illuminate\Http\Request;

class InternshipController extends Controller
{
    public function index(Request $request)
    {
        $query = Internship::with(['student.user', 'company'])->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type'))   $query->where('type', $request->type);
        $internships = $query->paginate(20)->withQueryString();
        return view('departmental.internships.index', compact('internships'));
    }

    public function create()
    {
        $students  = Student::with('user')->where('status', 'active')->get();
        $companies = Company::orderBy('name')->get();
        return view('departmental.internships.create', compact('students', 'companies'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'company_name'     => 'required|string|max:255',
            'company_id'       => 'nullable|exists:companies,id',
            'role_title'       => 'required|string|max:255',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after:start_date',
            'type'             => 'required|in:internship,industrial_training,live_project',
            'stipend'          => 'nullable|numeric|min:0',
            'supervisor_name'  => 'nullable|string|max:255',
            'supervisor_email' => 'nullable|email',
            'description'      => 'nullable|string',
        ]);
        Internship::create(array_merge($v, ['status' => 'ongoing', 'approved_by' => auth()->id()]));
        return redirect()->route('cmc.internships.index')->with('success', 'Internship registered.');
    }

    public function show(Internship $internship)
    {
        $internship->load(['student.user', 'company', 'approvedBy']);
        return view('departmental.internships.show', compact('internship'));
    }

    public function complete(Request $request, Internship $internship)
    {
        $request->validate([
            'feedback' => 'nullable|string',
            'rating'   => 'nullable|integer|min:1|max:5',
            'end_date' => 'required|date',
        ]);
        $internship->update([
            'status'   => 'completed',
            'end_date' => $request->end_date,
            'feedback' => $request->feedback,
            'rating'   => $request->rating,
        ]);
        return back()->with('success', 'Internship marked as completed.');
    }
}
