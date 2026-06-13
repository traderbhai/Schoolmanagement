<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\User;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentHierarchyController extends Controller
{
    private const OWNER_LEVEL_PERMISSIONS = [
        'manage_department_settings',
        'configure_department',
    ];

    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        $departments = $this->hierarchy->manageableDepartments($request->user());
        abort_if($departments->isEmpty(), 403);

        $department = $request->integer('department_id')
            ? Department::findOrFail($request->integer('department_id'))
            : $departments->first();
        abort_unless($department && $this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $department), 403);

        $roles = $department
            ? $department->departmentRoles()
                ->where('is_active', true)
                ->withCount(['members' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('level')
                ->orderBy('name')
                ->get()
            : collect();
        $teams = $department
            ? $department->departmentTeams()->with('parent')->where('is_active', true)->orderBy('name')->get()
            : collect();
        $members = $department
            ? $department->departmentMembers()->with(['user', 'role', 'team', 'manager.user'])->where('is_active', true)->get()->sortBy('role.level')
            : collect();
        $users = User::orderBy('name')
            ->get()
            ->reject(fn (User $user) => !$request->user()->hasRole('admin') && $user->hasRole('admin'))
            ->values();

        return view('admin.department-hierarchy.index', compact(
            'departments', 'department', 'roles', 'teams', 'members', 'users'
        ));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:1', 'max:999'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        abort_unless($this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $department), 403);
        abort_unless($this->hierarchy->canManageRoleLevel($request->user(), $department, (int) $validated['level']), 403);
        $permissions = collect($validated['permissions'] ?? [])->unique()->values();
        abort_if(
            (int) $validated['level'] > 10 && $permissions->intersect(self::OWNER_LEVEL_PERMISSIONS)->isNotEmpty(),
            422,
            'Department settings and hierarchy configuration permissions are only allowed for owner/head-level roles.'
        );

        $role = DepartmentRole::updateOrCreate(
            [
                'department_id' => $validated['department_id'],
                'code' => $validated['code'] ?: Str::slug($validated['name'], '_'),
            ],
            [
                'name' => $validated['name'],
                'level' => $validated['level'],
                'can_manage_lower_levels' => $request->boolean('can_manage_lower_levels'),
                'can_view_team_data' => $request->boolean('can_view_team_data'),
                'can_assign_work' => $request->boolean('can_assign_work'),
                'permissions' => $permissions->all(),
                'is_active' => true,
            ]
        );

        $this->hierarchy->recordActivity(
            $role->department,
            $request->user(),
            'department_role_saved',
            'Saved department role ' . $role->name . '.',
            $role
        );

        return back()->with('success', 'Department role saved.');
    }

    public function storeTeam(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'parent_id' => ['nullable', 'exists:department_teams,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:custom,region,program,source,campus,function'],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        abort_unless($this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $department), 403);
        $this->assertTeamBelongsToDepartment($validated['parent_id'] ?? null, $department);

        $team = DepartmentTeam::create($validated + ['is_active' => true]);

        $this->hierarchy->recordActivity(
            $team->department,
            $request->user(),
            'department_team_created',
            'Created department team ' . $team->name . '.',
            $team
        );

        return back()->with('success', 'Department team created.');
    }

    public function storeMember(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'department_role_id' => ['required', 'exists:department_roles,id'],
            'department_team_id' => ['nullable', 'exists:department_teams,id'],
            'user_id' => ['required', 'exists:users,id'],
            'reports_to_member_id' => ['nullable', 'exists:department_members,id'],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        abort_unless($this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $department), 403);

        $role = DepartmentRole::where('department_id', $department->id)
            ->where('id', $validated['department_role_id'])
            ->where('is_active', true)
            ->firstOrFail();
        abort_unless($this->hierarchy->canManageRoleLevel($request->user(), $department, (int) $role->level), 403);

        $user = User::findOrFail($validated['user_id']);
        abort_unless($request->user()->hasRole('admin') || !$user->hasRole('admin'), 403);
        $this->assertTeamBelongsToDepartment($validated['department_team_id'] ?? null, $department);

        $managerId = $validated['reports_to_member_id'] ?? null;
        if (!$request->user()->hasRole('admin')) {
            $actorMember = $this->hierarchy->membershipFor($request->user(), $department);
            abort_unless($actorMember, 403);
            $managerId = $managerId ?: $actorMember->id;
            abort_unless($this->hierarchy->manageableMemberIds($request->user(), $department)->contains($managerId), 403);
        }
        $this->assertMemberBelongsToDepartment($managerId, $department);
        $this->assertReportingManagerCanManageRole($managerId, $role, $user);

        $member = DepartmentMember::where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($member && (int) $member->department_role_id !== (int) $role->id) {
            $lowestDirectReportLevel = DepartmentMember::with('role')
                ->where('reports_to_member_id', $member->id)
                ->where('is_active', true)
                ->get()
                ->min(fn (DepartmentMember $report) => (int) ($report->role?->level ?? 999));
            abort_unless(!$lowestDirectReportLevel || (int) $role->level < $lowestDirectReportLevel, 422, 'Cannot move a manager below or beside active direct reports.');
        }

        if ($member) {
            $member->update([
                'department_role_id' => $role->id,
                'department_team_id' => $validated['department_team_id'] ?? null,
                'reports_to_member_id' => $managerId,
                'is_active' => true,
            ]);
        } else {
            $member = DepartmentMember::create([
                'department_id' => $department->id,
                'department_role_id' => $role->id,
                'department_team_id' => $validated['department_team_id'] ?? null,
                'user_id' => $user->id,
                'reports_to_member_id' => $managerId,
                'is_active' => true,
            ]);
        }

        $member->load(['department', 'user', 'role']);
        $this->hierarchy->recordActivity(
            $member->department,
            $request->user(),
            'department_member_saved',
            'Saved ' . $member->user->name . ' as ' . $member->role->name . '.',
            $member,
            $member->user
        );

        return back()->with('success', 'Department member saved.');
    }

    public function deactivateRole(Request $request, DepartmentRole $role)
    {
        $role->load('department');
        abort_unless($this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $role->department), 403);
        abort_unless($this->hierarchy->canManageRoleLevel($request->user(), $role->department, (int) $role->level), 403);
        abort_if($role->members()->where('is_active', true)->exists(), 422, 'Cannot deactivate a role that still has active members.');
        abort_if($this->isLastActiveOwnerRole($role), 422, 'Cannot deactivate the last active owner/head role for a department.');

        $role->update(['is_active' => false]);
        $this->hierarchy->recordActivity(
            $role->department,
            $request->user(),
            'department_role_deactivated',
            'Deactivated department role ' . $role->name . '.',
            $role
        );

        return back()->with('success', 'Department role deactivated.');
    }

    public function deactivateTeam(Request $request, DepartmentTeam $team)
    {
        $team->load('department');
        abort_unless($this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $team->department), 403);
        abort_if($team->members()->where('is_active', true)->exists(), 422, 'Cannot deactivate a team that still has active members.');
        abort_if($team->children()->where('is_active', true)->exists(), 422, 'Cannot deactivate a team that still has active child teams.');

        $team->update(['is_active' => false]);
        $this->hierarchy->recordActivity(
            $team->department,
            $request->user(),
            'department_team_deactivated',
            'Deactivated department team ' . $team->name . '.',
            $team
        );

        return back()->with('success', 'Department team deactivated.');
    }

    public function deactivateMember(Request $request, DepartmentMember $member)
    {
        $member->load(['department', 'role', 'user']);
        abort_unless($this->hierarchy->canConfigureDepartmentHierarchy($request->user(), $member->department), 403);
        abort_unless($this->hierarchy->canManageRoleLevel($request->user(), $member->department, (int) ($member->role?->level ?? 999)), 403);
        abort_if($member->user_id === $request->user()->id, 422, 'Cannot deactivate your own department membership.');
        abort_if($member->directReports()->where('is_active', true)->exists(), 422, 'Cannot deactivate a member who still has active direct reports.');
        if (!$request->user()->hasRole('admin')) {
            abort_unless($this->hierarchy->manageableMemberIds($request->user(), $member->department)->contains($member->id), 403);
        }

        $member->update(['is_active' => false]);
        $this->hierarchy->recordActivity(
            $member->department,
            $request->user(),
            'department_member_deactivated',
            'Deactivated department member ' . ($member->user?->name ?? 'user') . '.',
            $member,
            $member->user
        );

        return back()->with('success', 'Department member deactivated.');
    }

    private function assertTeamBelongsToDepartment(?int $teamId, Department $department): void
    {
        if (!$teamId) {
            return;
        }

        abort_unless(
            DepartmentTeam::where('department_id', $department->id)->where('id', $teamId)->where('is_active', true)->exists(),
            422
        );
    }

    private function assertMemberBelongsToDepartment(?int $memberId, Department $department): void
    {
        if (!$memberId) {
            return;
        }

        abort_unless(
            DepartmentMember::where('department_id', $department->id)->where('id', $memberId)->where('is_active', true)->exists(),
            422
        );
    }

    private function assertReportingManagerCanManageRole(?int $managerId, DepartmentRole $targetRole, User $targetUser): void
    {
        if (!$managerId) {
            return;
        }

        $manager = DepartmentMember::with('role')->findOrFail($managerId);

        abort_unless(
            $manager->department_id === $targetRole->department_id
                && $manager->is_active
                && $manager->user_id !== $targetUser->id
                && (bool) ($manager->role?->can_manage_lower_levels)
                && (int) ($manager->role?->level ?? 999) < (int) $targetRole->level,
            422
        );
    }

    private function isLastActiveOwnerRole(DepartmentRole $role): bool
    {
        if ((int) $role->level > 10) {
            return false;
        }

        return DepartmentRole::where('department_id', $role->department_id)
            ->where('id', '!=', $role->id)
            ->where('is_active', true)
            ->where('level', '<=', 10)
            ->get()
            ->filter(function (DepartmentRole $candidate) {
                return collect($candidate->permissions ?? [])
                    ->intersect(['manage_department_settings', 'configure_department'])
                    ->isNotEmpty();
            })
            ->isEmpty();
    }
}
