<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Teacher, User, Department};
use App\Services\TimetableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index(Request $request)
    {
        $this->authorizeAcademicIdentityManagement($request);

        $teachers = Teacher::with(['user','department'])
            ->when($request->department_id, fn($q,$v) => $q->where('department_id',$v))
            ->when($request->status, fn($q,$v) => $q->where('status',$v))
            ->when($request->search, fn($q,$v) =>
                $q->whereHas('user', fn($u) => $u->where('name','like',"%$v%"))
                  ->orWhere('employee_id','like',"%$v%")
            )
            ->latest()->paginate(20)->withQueryString();
        $departments = Department::where('is_active',true)->get();
        return view('admin.teachers.index', compact('teachers','departments'));
    }

    public function create()
    {
        $this->authorizeAcademicIdentityManagement(request());

        $departments = Department::where('is_active',true)->get();
        return view('admin.teachers.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $this->authorizeAcademicIdentityManagement($request);

        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users',
            'password'        => 'required|min:8',
            'department_id'   => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'employee_id'     => 'required|unique:teachers',
            'designation'     => 'nullable|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,visiting',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole('teacher');

        Teacher::create([...$request->only([
            'department_id','employee_id','designation','qualification',
            'specialization','phone','address','date_of_joining','employment_type','salary',
        ]), 'user_id' => $user->id]);

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher added successfully.');
    }

    public function show(Teacher $teacher)
    {
        $this->authorizeAcademicIdentityManagement(request());

        $teacher->load(['user','department','timetableEntries.subject','timetableEntries.slot']);
        $currentSemester = \App\Models\Semester::current();
        $weeklyLoad = $currentSemester ? $this->service->getTeacherWeeklyLoad($teacher->id, $currentSemester->id) : 0;
        return view('admin.teachers.show', compact('teacher','weeklyLoad','currentSemester'));
    }

    public function edit(Teacher $teacher)
    {
        $this->authorizeAcademicIdentityManagement(request());

        $departments = Department::where('is_active',true)->get();
        return view('admin.teachers.edit', compact('teacher','departments'));
    }

    public function update(Request $request, Teacher $teacher)
    {
        $this->authorizeAcademicIdentityManagement($request);

        if ($teacher->status === 'inactive') {
            return back()->with('error', 'Archived teacher profiles are locked. Use a dedicated audited reactivation or correction workflow instead of ordinary edit.');
        }

        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,'.$teacher->user_id,
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'status'        => 'required|in:active,inactive,on_leave',
        ]);

        if ($request->status === 'inactive') {
            return back()->with('error', 'Use the archive action to deactivate a teacher so roles and audit history are preserved.');
        }

        $teacher->user->update(['name' => $request->name, 'email' => $request->email]);
        $teacher->update($request->only([
            'department_id','designation','qualification','specialization',
            'phone','address','date_of_joining','employment_type','salary','status',
        ]));
        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Teacher updated.');
    }

    public function destroy(Teacher $teacher)
    {
        $this->authorizeAcademicIdentityManagement(request());

        $name = $teacher->user?->name ?? $teacher->employee_id;
        $teacher->update(['status' => 'inactive']);
        $teacher->user?->syncRoles([]);

        ActivityLog::record('archived', "Teacher archived instead of deleted to preserve teaching history: {$name}", $teacher);

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher archived. Timetable, attendance, leave, and academic history was preserved.');
    }

    private function authorizeAcademicIdentityManagement(Request $request): void
    {
        abort_unless($request->user() && AccessControl::canManageAcademicIdentities($request->user()), 403);
    }
}
