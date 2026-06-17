<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AcademicYearController extends Controller
{
    public function index() {
        $years = AcademicYear::withCount('semesters')->latest()->paginate(10);
        return view('admin.academic-years.index', compact('years'));
    }
    public function create() { return view('admin.academic-years.create'); }
    public function store(Request $request) {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_year' => 'required|digits:4',
            'end_year'   => 'required|digits:4|gte:start_year',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);
        if (!empty($data['is_current'])) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
        }
        AcademicYear::create($data);
        return redirect()->route('admin.academic-years.index')->with('success', 'Academic year created.');
    }
    public function show(AcademicYear $academicYear) {
        $academicYear->load('semesters');
        return view('admin.academic-years.show', compact('academicYear'));
    }
    public function edit(AcademicYear $academicYear) { return view('admin.academic-years.edit', compact('academicYear')); }
    public function update(Request $request, AcademicYear $academicYear) {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_year' => 'required|digits:4',
            'end_year'   => 'required|digits:4|gte:start_year',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if ($this->hasOperationalDependencies($academicYear) && $this->changesCalendarBoundaries($academicYear, $data)) {
            throw ValidationException::withMessages([
                'academic_year' => 'Academic year dates and year boundaries cannot be changed after semesters, batches, or fee structures are linked.',
            ]);
        }

        if (!empty($data['is_current'])) {
            AcademicYear::where('is_current', true)->where('id', '!=', $academicYear->id)->update(['is_current' => false]);
        }
        $academicYear->update($data);
        return redirect()->route('admin.academic-years.index')->with('success', 'Updated.');
    }
    public function destroy(AcademicYear $academicYear) {
        if ($this->hasOperationalDependencies($academicYear)) {
            return redirect()->route('admin.academic-years.index')
                ->with('error', 'Academic years with semesters, batches, or fee structures cannot be deleted because academic and financial history depends on them.');
        }

        $academicYear->delete();
        return redirect()->route('admin.academic-years.index')->with('success', 'Deleted.');
    }

    private function hasOperationalDependencies(AcademicYear $academicYear): bool
    {
        return $academicYear->semesters()->exists()
            || $academicYear->feeStructures()->exists()
            || $academicYear->batches()->exists();
    }

    private function changesCalendarBoundaries(AcademicYear $academicYear, array $data): bool
    {
        return (string) $academicYear->start_year !== (string) $data['start_year']
            || (string) $academicYear->end_year !== (string) $data['end_year']
            || $academicYear->start_date->toDateString() !== (string) $data['start_date']
            || $academicYear->end_date->toDateString() !== (string) $data['end_date'];
    }
}
