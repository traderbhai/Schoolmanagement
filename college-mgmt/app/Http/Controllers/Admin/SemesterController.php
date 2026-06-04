<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Semester, AcademicYear};
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index() {
        $semesters = Semester::with('academicYear')->latest()->paginate(15);
        return view('admin.semesters.index', compact('semesters'));
    }
    public function create() {
        $years = AcademicYear::all();
        return view('admin.semesters.create', compact('years'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'name'             => 'required|string|max:100',
            'number'           => 'required|integer|min:1',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'is_current'       => 'boolean',
        ]);
        if (!empty($data['is_current'])) {
            Semester::where('is_current', true)->update(['is_current' => false]);
        }
        Semester::create($data);
        return redirect()->route('admin.semesters.index')->with('success', 'Semester created.');
    }
    public function show(Semester $semester) {
        $semester->load('academicYear');
        return view('admin.semesters.show', compact('semester'));
    }
    public function edit(Semester $semester) {
        $years = AcademicYear::all();
        return view('admin.semesters.edit', compact('semester','years'));
    }
    public function update(Request $request, Semester $semester) {
        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'name'             => 'required|string|max:100',
            'number'           => 'required|integer|min:1',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'is_current'       => 'boolean',
        ]);
        if (!empty($data['is_current'])) {
            Semester::where('is_current', true)->where('id','!=',$semester->id)->update(['is_current' => false]);
        }
        $semester->update($data);
        return redirect()->route('admin.semesters.index')->with('success', 'Updated.');
    }
    public function destroy(Semester $semester) {
        $semester->delete();
        return redirect()->route('admin.semesters.index')->with('success', 'Deleted.');
    }
}
