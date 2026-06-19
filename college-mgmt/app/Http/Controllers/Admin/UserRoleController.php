<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Models\UserRole;
use App\Models\Program;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function index()
    {
        $this->authorizeRoleAssignmentManagement();

        $userRoles = UserRole::with(['user', 'role', 'program', 'assignedBy'])
            ->latest()
            ->paginate(30);

        return view('admin.users.roles.index', compact('userRoles'));
    }

    public function create()
    {
        $this->authorizeRoleAssignmentManagement();

        $users = User::where('email', '!=', 'admin@college.com')
            ->orderBy('name')
            ->get();
        $roles = Role::orderBy('name')->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.roles.create', compact('users', 'roles', 'programs'));
    }

    public function store(Request $request)
    {
        $this->authorizeRoleAssignmentManagement();

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
            'program_id' => [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')->where('is_active', true),
            ],
            'active_until' => 'nullable|date|after:today',
        ]);

        $this->validateGlobalRoleScope($request);

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

        $userRole = UserRole::updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'program_id' => $validated['program_id'] ?? null,
            ],
            [
                'assigned_by' => auth()->id(),
                'active_until' => $validated['active_until'] ?? null,
            ]
        );

        if (! $user->hasRole($role->name)) {
            $user->assignRole($role->name);
        }

        // Audit log
        AuditLog::logRoleAssignment(auth()->user(), $user, $role,
            ($validated['program_id'] ?? null) ? Program::find($validated['program_id']) : null
        );

        return redirect('admin/users/roles')->with('success', 'Role assigned successfully.');
    }

    public function destroy(UserRole $userRole)
    {
        $this->authorizeRoleAssignmentManagement();

        $user = $userRole->user;
        $role = $userRole->role;

        if ($role->name === 'admin' && $this->wouldRemoveProtectedAdminAccess($user)) {
            return back()->with('error', 'Cannot revoke the final or current admin access. Assign another admin first.');
        }

        AuditLog::logRoleRevoked(auth()->user(), $user, $role);

        $userRole->update(['active_until' => today()->subDay()]);

        if (! $this->hasOtherActiveAssignmentForRole($user, $role->name)) {
            $user->removeRole($role->name);
        }

        return back()->with('success', 'Role revoked successfully.');
    }

    public function expireAll(User $user)
    {
        $this->authorizeRoleAssignmentManagement();

        $userRoles = UserRole::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('active_until')
                    ->orWhere('active_until', '>=', today());
            })
            ->get();

        foreach ($userRoles as $ur) {
            if ($ur->role?->name === 'admin' && $this->wouldRemoveProtectedAdminAccess($user)) {
                continue;
            }

            $ur->update(['active_until' => today()->subDay()]);
            AuditLog::logRoleRevoked(auth()->user(), $user, $ur->role);

            if ($ur->role && ! $this->hasOtherActiveAssignmentForRole($user, $ur->role->name)) {
                $user->removeRole($ur->role->name);
            }
        }

        return back()->with('success', 'All active roles revoked for ' . $user->name);
    }

    private function hasOtherActiveAssignmentForRole(User $user, string $roleName): bool
    {
        $role = Role::where('name', $roleName)->first();

        $hasUserRole = $role && UserRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->get()
            ->contains(fn (UserRole $assignment) => $assignment->isActive());

        if ($hasUserRole) {
            return true;
        }

        return \App\Models\RoleProgramAssignment::where('user_id', $user->id)
            ->where('role_name', $roleName)
            ->where('is_active', true)
            ->exists();
    }

    private function wouldRemoveProtectedAdminAccess(User $user): bool
    {
        if (auth()->id() === $user->id) {
            return true;
        }

        $otherAdminCount = User::role('admin')
            ->whereKeyNot($user->id)
            ->count();

        return $otherAdminCount === 0;
    }

    private function authorizeRoleAssignmentManagement(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageRoleAssignments(auth()->user()), 403);
    }

    private function validateGlobalRoleScope(Request $request): void
    {
        if (! $request->filled('program_id')) {
            return;
        }

        back()->withErrors([
            'program_id' => 'Global user roles cannot be program-scoped from this page. Use Scoped Role Assignments for program-level academic access.',
        ])->throwResponse();
    }
}
