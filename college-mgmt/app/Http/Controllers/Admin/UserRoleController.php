<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Models\UserRole;
use App\Models\Program;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function index()
    {
        $userRoles = UserRole::with(['user', 'role', 'program', 'assignedBy'])
            ->latest()
            ->paginate(30);

        return view('admin.users.roles.index', compact('userRoles'));
    }

    public function create()
    {
        $users = User::where('email', '!=', 'admin@college.com')
            ->orderBy('name')
            ->get();
        $roles = Role::orderBy('name')->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.roles.create', compact('users', 'roles', 'programs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
            'program_id' => 'nullable|integer|exists:programs,id',
            'active_until' => 'nullable|date|after:today',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        // Check if already assigned
        $existing = UserRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('program_id', $validated['program_id'] ?? null)
            ->first();

        if ($existing && $existing->isActive()) {
            return back()->with('error', 'This role is already assigned to this user.');
        }

        $userRole = UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'program_id' => $validated['program_id'] ?? null,
            'assigned_by' => auth()->id(),
            'active_until' => $validated['active_until'] ?? null,
        ]);

        // Audit log
        AuditLog::logRoleAssignment(auth()->user(), $user, $role,
            $validated['program_id'] ? Program::find($validated['program_id']) : null
        );

        return redirect('admin/users/roles')->with('success', 'Role assigned successfully.');
    }

    public function destroy(UserRole $userRole)
    {
        $user = $userRole->user;
        $role = $userRole->role;

        AuditLog::logRoleRevoked(auth()->user(), $user, $role);

        $userRole->delete();

        return back()->with('success', 'Role revoked successfully.');
    }

    public function expireAll(User $user)
    {
        $userRoles = UserRole::where('user_id', $user->id)
            ->where('active_until', '>', today())
            ->get();

        foreach ($userRoles as $ur) {
            $ur->update(['active_until' => today()]);
            AuditLog::logRoleRevoked(auth()->user(), $user, $ur->role);
        }

        return back()->with('success', 'All active roles revoked for ' . $user->name);
    }
}
