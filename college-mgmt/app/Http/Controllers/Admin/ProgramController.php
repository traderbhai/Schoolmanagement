<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Program, Department};
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::with('department', 'batches')->withCount('students', 'subjects', 'batches')->get();
        return view('admin.programs.index', compact('programs'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        return view('admin.programs.create', compact('departments'));
    }

    public function store(Request $r)
    {
        $r->validate([
            'name'          => 'required|string|max:191',
            'code'          => 'required|string|max:20|unique:programs,code',
            'department_id' => 'required|exists:departments,id',
            'system_type'   => 'required|in:semester,trimester,annual,quarter',
            'duration_years'=> 'required|integer|min:1|max:5',
            'total_terms'   => 'required|integer|min:1|max:12',
        ]);
        Program::create($r->all());
        return redirect()->route('admin.programs.index')->with('success', 'Program created.');
    }

    public function show(Program $program)
    {
        $program->load('department', 'specializations', 'batches.terms', 'batches.academicYear');
        $studentCount = $program->students()->count();
        $activeBatch  = $program->batches()->where('status', 'active')->with('terms')->first();
        return view('admin.programs.show', compact('program', 'studentCount', 'activeBatch'));
    }

    public function edit(Program $program)
    {
        $departments = Department::where('is_active', true)->get();
        return view('admin.programs.edit', compact('program', 'departments'));
    }

    public function update(Request $r, Program $program)
    {
        $r->validate([
            'name'          => 'required|string|max:191',
            'code'          => 'required|string|max:20|unique:programs,code,' . $program->id,
            'department_id' => 'required|exists:departments,id',
            'system_type'   => 'required|in:semester,trimester,annual,quarter',
            'duration_years'=> 'required|integer|min:1|max:5',
            'total_terms'   => 'required|integer|min:1|max:12',
        ]);
        $program->update($r->all());
        return redirect()->route('admin.programs.index')->with('success', 'Program updated.');
    }

    public function destroy(Program $program)
    {
        if ($program->students()->count() > 0) {
            return back()->with('error', 'Cannot delete program with enrolled students.');
        }
        $program->delete();
        return redirect()->route('admin.programs.index')->with('success', 'Program deleted.');
    }
}
