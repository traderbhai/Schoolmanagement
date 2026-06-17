<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Semester, AcademicYear};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        if ($this->hasOperationalDependencies($semester) && $this->changesOperationalBoundaries($semester, $data)) {
            throw ValidationException::withMessages([
                'semester' => 'Semester academic year, number, and dates cannot be changed after enrollments, exams, or timetable entries are linked.',
            ]);
        }

        if (!empty($data['is_current'])) {
            Semester::where('is_current', true)->where('id','!=',$semester->id)->update(['is_current' => false]);
        }
        $semester->update($data);
        return redirect()->route('admin.semesters.index')->with('success', 'Updated.');
    }
    public function destroy(Semester $semester) {
        if ($this->hasOperationalDependencies($semester)) {
            return redirect()->route('admin.semesters.index')
                ->with('error', 'Semesters with enrollments, exams, or timetable entries cannot be deleted because academic history depends on them.');
        }

        $semester->delete();
        return redirect()->route('admin.semesters.index')->with('success', 'Deleted.');
    }

    private function hasOperationalDependencies(Semester $semester): bool
    {
        return $semester->enrollments()->exists()
            || $semester->exams()->exists()
            || $semester->timetableEntries()->exists();
    }

    private function changesOperationalBoundaries(Semester $semester, array $data): bool
    {
        return (int) $semester->academic_year_id !== (int) $data['academic_year_id']
            || (int) $semester->number !== (int) $data['number']
            || $semester->start_date->toDateString() !== (string) $data['start_date']
            || $semester->end_date->toDateString() !== (string) $data['end_date'];
    }
}
