<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Subject, Department};
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index() {
        $subjects = Subject::with('department')->paginate(20);
        return view('admin.subjects.index', compact('subjects'));
    }
    public function create() {
        $departments = Department::where('is_active',true)->get();
        return view('admin.subjects.create', compact('departments'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'department_id'  => 'required|exists:departments,id',
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:20|unique:subjects',
            'description'    => 'nullable|string',
            'credits'        => 'required|integer|min:1|max:10',
            'type'           => 'required|in:theory,practical,tutorial',
            'hours_per_week' => 'required|integer|min:1|max:20',
        ]);
        Subject::create($data);
        return redirect()->route('admin.subjects.index')->with('success', 'Subject created.');
    }
    public function show(Subject $subject) {
        return view('admin.subjects.show', compact('subject'));
    }
    public function edit(Subject $subject) {
        $departments = Department::where('is_active',true)->get();
        return view('admin.subjects.edit', compact('subject','departments'));
    }
    public function update(Request $request, Subject $subject) {
        $data = $request->validate([
            'department_id'  => 'required|exists:departments,id',
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:20|unique:subjects,code,'.$subject->id,
            'description'    => 'nullable|string',
            'credits'        => 'required|integer|min:1|max:10',
            'type'           => 'required|in:theory,practical,tutorial',
            'hours_per_week' => 'required|integer|min:1|max:20',
            'is_active'      => 'boolean',
        ]);
        $subject->update($data);
        return redirect()->route('admin.subjects.index')->with('success', 'Updated.');
    }
    public function destroy(Subject $subject) {
        $subject->delete();
        return redirect()->route('admin.subjects.index')->with('success', 'Deleted.');
    }
}
