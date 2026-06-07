<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{AlumniProfile, Student};
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    public function index(Request $request)
    {
        $query = AlumniProfile::with(['student.user', 'student.program'])->latest();
        if ($request->filled('graduation_year')) $query->where('graduation_year', $request->graduation_year);
        if ($request->filled('verified')) $query->where('is_verified', $request->verified === '1');
        $alumni = $query->paginate(20)->withQueryString();
        $years  = AlumniProfile::distinct()->pluck('graduation_year')->sortDesc();
        return view('departmental.alumni.index', compact('alumni', 'years'));
    }

    public function create()
    {
        $students = Student::with('user')->whereIn('status', ['graduated', 'active'])->get();
        return view('departmental.alumni.create', compact('students'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'graduation_year'  => 'required|integer|min:2000|max:' . now()->year,
            'current_employer' => 'nullable|string|max:255',
            'current_role'     => 'nullable|string|max:255',
            'current_salary'   => 'nullable|numeric|min:0',
            'city'             => 'nullable|string|max:100',
            'linkedin_url'     => 'nullable|url|max:255',
            'feedback'         => 'nullable|string',
        ]);
        AlumniProfile::updateOrCreate(['student_id' => $v['student_id']], $v);
        return redirect()->route('cmc.alumni.index')->with('success', 'Alumni profile saved.');
    }

    public function verify(AlumniProfile $alumniProfile)
    {
        $alumniProfile->update(['is_verified' => true]);
        return back()->with('success', 'Alumni profile verified.');
    }
}
