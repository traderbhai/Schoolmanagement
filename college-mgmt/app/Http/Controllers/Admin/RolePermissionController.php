<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\RolePermissionMatrix;
use App\Models\Program;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function index()
    {
        $this->authorizeRolePermissionManagement();

        $roles = Role::with('permissions')->orderBy('name')->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('admin.roles.permissions.index', compact('roles', 'programs'));
    }

    public function hierarchy()
    {
        $this->authorizeRolePermissionManagement();

        $roles = Role::with('permissions')->orderBy('name')->get();

        return view('admin.roles.hierarchy', compact('roles'));
    }

    public function show(Role $role)
    {
        $this->authorizeRolePermissionManagement();

        $role->load('permissions');
        $permissions = Permission::orderBy('name')->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        $rolePermissions = RolePermissionMatrix::where('role_id', $role->id)
            ->with('program')
            ->get()
            ->groupBy('program_id');

        return view('admin.roles.permissions.show', compact('role', 'permissions', 'programs', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeRolePermissionManagement();

        $validated = $request->validate([
            'permissions'   => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
            'program_id'    => [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')->where('is_active', true),
            ],
        ]);

        $programId = $validated['program_id'] ?? null;
        $permissionIds = $validated['permissions'] ?? [];

        RolePermissionMatrix::where('role_id', $role->id)
            ->where('program_id', $programId)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            RolePermissionMatrix::create([
                'role_id'          => $role->id,
                'permission_id'    => $permissionId,
                'program_id'       => $programId,
                'program_specific' => $programId !== null,
            ]);
        }

        AuditLog::logPermissionChanged(auth()->user(), $role, [
            'summary'          => 'Permissions updated',
            'program_id'       => $programId,
            'permissions_count'=> count($permissionIds),
        ]);

        return back()->with('success', 'Permissions updated for ' . $role->name);
    }

    private function authorizeRolePermissionManagement(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageRoleAssignments(auth()->user()), 403);
    }
}
