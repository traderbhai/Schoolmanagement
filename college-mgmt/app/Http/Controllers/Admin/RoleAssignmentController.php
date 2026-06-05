<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Program, Batch, RoleProgramAssignment};
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleAssignmentController extends Controller
{
    private const DEPARTMENTAL_ROLES = [
        'dean_academics'  => 'Dean of Academics — view all programs, student academics, exam results, attendance',
        'program_chair'   => 'Program Chair — manage assigned program(s): curriculum, subjects, timetable, students',
        'exam_cell'       => 'Exam Cell — manage exams, results, grade sheets for all programs',
        'hod'             => 'Head of Department — department-scoped management similar to program_chair',
        'accounts_officer'=> 'Accounts Officer — manage fee structures, payment records, generate fee reports',
    ];

    public function index()
    {
        $assignments = RoleProgramAssignment::with(['user', 'program', 'batch', 'assignedBy'])
            ->orderBy('role_name')
            ->get()
            ->groupBy('role_name');

        $roles = array_keys(self::DEPARTMENTAL_ROLES);

        return view('admin.role-assignments.index', compact('assignments', 'roles'));
    }

    public function create()
    {
        $users    = User::orderBy('name')->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $batches  = Batch::orderBy('id', 'desc')->get();
        $roles    = self::DEPARTMENTAL_ROLES;

        return view('admin.role-assignments.create', compact('users', 'programs', 'batches', 'roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'role_name'  => 'required|in:' . implode(',', array_keys(self::DEPARTMENTAL_ROLES)),
            'program_id' => 'nullable|exists:programs,id',
            'batch_id'   => 'nullable|exists:batches,id',
        ]);

        $admin = auth()->user();

        $assignment = RoleProgramAssignment::firstOrCreate(
            [
                'user_id'    => $request->user_id,
                'role_name'  => $request->role_name,
                'program_id' => $request->program_id ?: null,
            ],
            [
                'batch_id'    => $request->batch_id ?: null,
                'is_active'   => true,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
            ]
        );

        // Ensure the user has the Spatie role
        $user = User::findOrFail($request->user_id);
        if (!$user->hasRole($request->role_name)) {
            Role::firstOrCreate(['name' => $request->role_name]);
            $user->assignRole($request->role_name);
        }

        return redirect()->route('admin.role-assignments.index')
            ->with('success', 'Role assignment created successfully.');
    }

    public function destroy(RoleProgramAssignment $assignment)
    {
        $user     = $assignment->user;
        $roleName = $assignment->role_name;

        $assignment->delete();

        // Keep Spatie role only if there are other active assignments with this role
        $hasOtherAssignments = RoleProgramAssignment::where('user_id', $user->id)
            ->where('role_name', $roleName)
            ->where('is_active', true)
            ->exists();

        if (!$hasOtherAssignments) {
            $user->removeRole($roleName);
        }

        return back()->with('success', 'Role assignment removed.');
    }
}
