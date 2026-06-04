<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Course, Department};
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index() {
        $courses = Course::with('department')->withCount('students')->paginate(15);
        return view('admin.courses.index', compact('courses'));
    }
    public function create() {
        $departments = Department::where('is_active',true)->get();
        return view('admin.courses.create', compact('departments'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'department_id'   => 'required|exists:departments,id',
            'name'            => 'required|string|max:255',
            'code'            => 'required|string|max:20|unique:courses',
            'description'     => 'nullable|string',
            'duration_years'  => 'required|integer|min:1|max:6',
            'total_semesters' => 'required|integer|min:1|max:12',
        ]);
        Course::create($data);
        return redirect()->route('admin.courses.index')->with('success', 'Course created.');
    }
    public function show(Course $course) {
        $course->load(['department','students.user']);
        return view('admin.courses.show', compact('course'));
    }
    public function edit(Course $course) {
        $departments = Department::where('is_active',true)->get();
        return view('admin.courses.edit', compact('course','departments'));
    }
    public function update(Request $request, Course $course) {
        $data = $request->validate([
            'department_id'   => 'required|exists:departments,id',
            'name'            => 'required|string|max:255',
            'code'            => 'required|string|max:20|unique:courses,code,'.$course->id,
            'description'     => 'nullable|string',
            'duration_years'  => 'required|integer|min:1|max:6',
            'total_semesters' => 'required|integer|min:1|max:12',
            'is_active'       => 'boolean',
        ]);
        $course->update($data);
        return redirect()->route('admin.courses.index')->with('success', 'Updated.');
    }
    public function destroy(Course $course) {
        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Deleted.');
    }
}
