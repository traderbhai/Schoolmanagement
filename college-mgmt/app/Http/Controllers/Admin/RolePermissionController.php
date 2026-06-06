<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermissionMatrix;
use App\Models\Program;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('admin.roles.permissions.index', compact('roles', 'programs'));
    }

    public function show(Role $role)
    {
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
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
            'program_id' => 'nullable|integer|exists:programs,id',
        ]);

        $programId = $validated['program_id'] ?? null;
        $permissionIds = $validated['permissions'] ?? [];

        // Remove existing for this program/role combo
        RolePermissionMatrix::where('role_id', $role->id)
            ->where('program_id', $programId)
            ->delete();

        // Add new permissions
        foreach ($permissionIds as $permissionId) {
            RolePermissionMatrix::create([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'program_id' => $programId,
                'program_specific' => $programId !== null,
            ]);
        }

        // Audit log
        AuditLog::logPermissionChanged(auth()->user(), $role, [
            'program_id' => $programId,
            'permissions_count' => count($permissionIds),
        ]);

        return back()->with('success', 'Permissions updated for ' . $role->name);
    }

    public function hierarchy()
    {
        $roles = Role::orderBy('name')->get();
        $hierarchy = [
            'admin' => ['dean_academics', 'accounts_officer', 'exam_cell'],
            'dean_academics' => ['program_chair', 'hod'],
            'program_chair' => ['faculty'],
            'hod' => ['faculty'],
        ];

        return view('admin.roles.hierarchy', compact('roles', 'hierarchy'));
    }
}
