<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Program, Department};
use App\Services\AcademicMasterDataIntegrityService;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function __construct(private AcademicMasterDataIntegrityService $integrity) {}

    public function index()
    {
        $this->authorizeAcademicStructure();

        $programs = Program::with('department', 'batches')->withCount('students', 'subjects', 'batches')->get();
        return view('admin.programs.index', compact('programs'));
    }

    public function create()
    {
        $this->authorizeAcademicStructure();

        $departments = Department::where('is_active', true)->get();
        return view('admin.programs.create', compact('departments'));
    }

    public function store(Request $r)
    {
        $this->authorizeAcademicStructure();

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
        $this->authorizeAcademicStructure();

        $program->load('department', 'specializations', 'batches.terms', 'batches.academicYear');
        $studentCount = $program->students()->count();
        $activeBatch  = $program->batches()->where('status', 'active')->with('terms')->first();
        return view('admin.programs.show', compact('program', 'studentCount', 'activeBatch'));
    }

    public function edit(Program $program)
    {
        $this->authorizeAcademicStructure();

        $departments = Department::where('is_active', true)->get();
        return view('admin.programs.edit', compact('program', 'departments'));
    }

    public function update(Request $r, Program $program)
    {
        $this->authorizeAcademicStructure();

        $validated = $r->validate([
            'name'          => 'required|string|max:191',
            'code'          => 'required|string|max:20|unique:programs,code,' . $program->id,
            'department_id' => 'required|exists:departments,id',
            'system_type'   => 'required|in:semester,trimester,annual,quarter',
            'duration_years'=> 'required|integer|min:1|max:5',
            'total_terms'   => 'required|integer|min:1|max:12',
        ]);

        $structuralFields = ['department_id', 'system_type', 'duration_years', 'total_terms'];
        $changesStructure = collect($structuralFields)->contains(
            fn (string $field) => (string) $program->{$field} !== (string) $validated[$field]
        );

        if ($changesStructure && $this->integrity->hasDependencies('program', $program->id)) {
            return back()->withErrors([
                'program' => 'Program structure is locked because students, batches, subjects, timetable, exams, or PMC records already depend on it. Deactivate or create a revised program instead.',
            ])->withInput();
        }

        $program->update($validated);
        return redirect()->route('admin.programs.index')->with('success', 'Program updated.');
    }

    public function destroy(Program $program)
    {
        $this->authorizeAcademicStructure();

        $dependencies = $this->integrity->dependencyLabels('program', $program->id);

        if ($dependencies !== []) {
            return back()->with('error', $this->integrity->message('program', $dependencies));
        }

        $program->delete();
        return redirect()->route('admin.programs.index')->with('success', 'Program deleted.');
    }

    private function authorizeAcademicStructure(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicStructure(auth()->user()), 403);
    }
}
