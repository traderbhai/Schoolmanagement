<?php
namespace App\Http\Controllers\Admin;
use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Course, Department};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index() {
        $this->authorizeAcademicStructure();

        $courses = Course::with('department')->withCount('students')->paginate(15);
        return view('admin.courses.index', compact('courses'));
    }
    public function create() {
        $this->authorizeAcademicStructure();

        $departments = Department::where('is_active',true)->get();
        return view('admin.courses.create', compact('departments'));
    }
    public function store(Request $request) {
        $this->authorizeAcademicStructure();

        $data = $request->validate([
            'department_id'   => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
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
        $this->authorizeAcademicStructure();

        $course->load(['department','students.user']);
        return view('admin.courses.show', compact('course'));
    }
    public function edit(Course $course) {
        $this->authorizeAcademicStructure();

        $departments = Department::where('is_active',true)->get();
        return view('admin.courses.edit', compact('course','departments'));
    }
    public function update(Request $request, Course $course) {
        $this->authorizeAcademicStructure();

        $data = $request->validate([
            'department_id'   => 'required|exists:departments,id',
            'name'            => 'required|string|max:255',
            'code'            => 'required|string|max:20|unique:courses,code,'.$course->id,
            'description'     => 'nullable|string',
            'duration_years'  => 'required|integer|min:1|max:6',
            'total_semesters' => 'required|integer|min:1|max:12',
            'is_active'       => 'boolean',
        ]);

        if ($this->movesToInactiveDepartment($course, $data)) {
            throw ValidationException::withMessages([
                'department_id' => 'Courses can be assigned only to active departments.',
            ]);
        }

        if ($this->hasOperationalDependencies($course) && $this->changesStructuralFields($course, $data)) {
            throw ValidationException::withMessages([
                'course' => 'Course department, code, duration, and semester structure cannot be changed after students, fees, admissions, exams, or timetable entries are linked.',
            ]);
        }

        if ($this->deactivatesCourseWithActiveStudents($course, $data)) {
            throw ValidationException::withMessages([
                'is_active' => 'Courses with active students cannot be deactivated.',
            ]);
        }

        $course->update($data);
        return redirect()->route('admin.courses.index')->with('success', 'Updated.');
    }
    public function destroy(Course $course) {
        $this->authorizeAcademicStructure();

        if ($this->hasOperationalDependencies($course)) {
            return redirect()->route('admin.courses.index')
                ->with('error', 'Courses with students, fees, admissions, exams, or timetable entries cannot be deleted because academic history depends on them.');
        }

        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Deleted.');
    }

    private function hasOperationalDependencies(Course $course): bool
    {
        return $course->students()->exists()
            || $course->feeStructures()->exists()
            || $course->timetableEntries()->exists()
            || $course->admissions()->exists()
            || $course->exams()->exists();
    }

    private function changesStructuralFields(Course $course, array $data): bool
    {
        return (int) $course->department_id !== (int) $data['department_id']
            || (string) $course->code !== (string) $data['code']
            || (int) $course->duration_years !== (int) $data['duration_years']
            || (int) $course->total_semesters !== (int) $data['total_semesters'];
    }

    private function deactivatesCourseWithActiveStudents(Course $course, array $data): bool
    {
        return array_key_exists('is_active', $data)
            && ! (bool) $data['is_active']
            && (bool) $course->is_active
            && $course->students()->where('status', 'active')->exists();
    }

    private function movesToInactiveDepartment(Course $course, array $data): bool
    {
        $departmentId = (int) $data['department_id'];

        return $departmentId !== (int) $course->department_id
            && Department::whereKey($departmentId)->where('is_active', false)->exists();
    }

    private function authorizeAcademicStructure(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicStructure(auth()->user()), 403);
    }
}
