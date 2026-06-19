<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AcademicMasterDataIntegrityService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(private AcademicMasterDataIntegrityService $integrity) {}

    public function index()
    {
        $this->authorizeAcademicStructure();

        $departments = Department::withCount(['courses','teachers','students'])->paginate(15);
        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        $this->authorizeAcademicStructure();

        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAcademicStructure();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:20|unique:departments',
            'description' => 'nullable|string',
            'head_name'   => 'nullable|string|max:255',
        ]);
        Department::create($data);
        return redirect()->route('admin.departments.index')->with('success', 'Department created.');
    }

    public function show(Department $department)
    {
        $this->authorizeAcademicStructure();

        $department->loadCount(['courses','teachers','students']);
        return view('admin.departments.show', compact('department'));
    }

    public function edit(Department $department)
    {
        $this->authorizeAcademicStructure();

        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeAcademicStructure();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:20|unique:departments,code,'.$department->id,
            'description' => 'nullable|string',
            'head_name'   => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);
        $department->update($data);
        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $this->authorizeAcademicStructure();

        $dependencies = $this->integrity->dependencyLabels('department', $department->id);

        if ($dependencies !== []) {
            return back()->with('error', $this->integrity->message('department', $dependencies));
        }

        $department->delete();
        return redirect()->route('admin.departments.index')->with('success', 'Department deleted.');
    }

    private function authorizeAcademicStructure(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicStructure(auth()->user()), 403);
    }
}
