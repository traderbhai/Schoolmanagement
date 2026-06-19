<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, ParentProfile, Student, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ParentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAcademicIdentityManagement($request);

        $parents = ParentProfile::with('user', 'students')
            ->when($request->search, function ($q) use ($request) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%"))
                  ->orWhere('phone', 'like', "%{$request->search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.parents.index', compact('parents'));
    }

    public function create()
    {
        $this->authorizeAcademicIdentityManagement(request());

        $students = Student::with('user')->where('status', 'active')->get();
        return view('admin.parents.create', compact('students'));
    }

    public function store(Request $request)
    {
        $this->authorizeAcademicIdentityManagement($request);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:8',
            'relation'      => 'required|in:father,mother,guardian,parent',
            'phone'         => 'nullable|string|max:20',
            'occupation'    => 'nullable|string|max:255',
            'annual_income' => 'nullable|string|max:100',
            'address'       => 'nullable|string',
            'student_ids'   => 'nullable|array',
            'student_ids.*' => [Rule::exists('students', 'id')->where('status', 'active')],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $role = Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $user->assignRole($role);

        $parent = ParentProfile::create([
            'user_id'       => $user->id,
            'relation'      => $data['relation'],
            'phone'         => $data['phone'] ?? null,
            'occupation'    => $data['occupation'] ?? null,
            'annual_income' => $data['annual_income'] ?? null,
            'address'       => $data['address'] ?? null,
        ]);

        if (!empty($data['student_ids'])) {
            $parent->students()->sync($data['student_ids']);
        }

        return redirect()->route('admin.parents.index')->with('success', 'Parent account created successfully.');
    }

    public function show(ParentProfile $parent)
    {
        $this->authorizeAcademicIdentityManagement(request());

        $parent->load('user', 'students.user', 'students.course', 'students.department');
        return view('admin.parents.show', compact('parent'));
    }

    public function edit(ParentProfile $parent)
    {
        $this->authorizeAcademicIdentityManagement(request());

        $linkedIds = $parent->students()->pluck('students.id')->toArray();
        $students = Student::with('user')
            ->where('status', 'active')
            ->orWhereIn('id', $linkedIds)
            ->get();
        return view('admin.parents.edit', compact('parent', 'students', 'linkedIds'));
    }

    public function update(Request $request, ParentProfile $parent)
    {
        $this->authorizeAcademicIdentityManagement($request);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $parent->user_id,
            'relation'      => 'required|in:father,mother,guardian,parent',
            'phone'         => 'nullable|string|max:20',
            'occupation'    => 'nullable|string|max:255',
            'annual_income' => 'nullable|string|max:100',
            'address'       => 'nullable|string',
            'student_ids'   => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $submittedStudentIds = collect($data['student_ids'] ?? [])->map(fn($id) => (int) $id)->unique()->values();
        $alreadyLinkedIds = $parent->students()->pluck('students.id')->map(fn($id) => (int) $id);
        $inactiveNewIds = Student::whereIn('id', $submittedStudentIds->diff($alreadyLinkedIds))
            ->where('status', '!=', 'active')
            ->pluck('id');

        if ($inactiveNewIds->isNotEmpty()) {
            return back()
                ->withErrors(['student_ids' => 'New parent links can be added only for active student profiles.'])
                ->withInput();
        }

        $parent->user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);

        $parent->update([
            'relation'      => $data['relation'],
            'phone'         => $data['phone'] ?? null,
            'occupation'    => $data['occupation'] ?? null,
            'annual_income' => $data['annual_income'] ?? null,
            'address'       => $data['address'] ?? null,
        ]);

        $parent->students()->sync($submittedStudentIds->all());

        return redirect()->route('admin.parents.index')->with('success', 'Parent updated successfully.');
    }

    public function destroy(ParentProfile $parent)
    {
        $this->authorizeAcademicIdentityManagement(request());

        $name = $parent->user?->name ?? 'Parent';

        if ($parent->user?->hasRole('parent')) {
            $parent->user->removeRole('parent');
        }

        $parent->delete();

        ActivityLog::record('archived', "Parent archived instead of deleted to preserve student linkage history: {$name}", $parent);

        return redirect()->route('admin.parents.index')->with('success', 'Parent archived. Student linkage and portal history was preserved.');
    }

    private function authorizeAcademicIdentityManagement(Request $request): void
    {
        abort_unless($request->user() && AccessControl::canManageAcademicIdentities($request->user()), 403);
    }
}
