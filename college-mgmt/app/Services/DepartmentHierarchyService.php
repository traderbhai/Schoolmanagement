<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\DepartmentFeatureSetting;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\User;
use Illuminate\Support\Collection;

class DepartmentHierarchyService
{
    public const ADMISSION_ROLE_NAMES = [
        'admission_director',
        'admission_head',
        'admission_manager',
        'jr_admission_manager',
        'admission_counsellor',
        'admission_telecaller',
        'admission_officer',
    ];

    private const ROLE_DEPARTMENT_MEMBERSHIPS = [
        'admission_director' => [['ADM', 'admission_director']],
        'admission_head' => [['ADM', 'admission_head']],
        'admission_manager' => [['ADM', 'admission_manager']],
        'jr_admission_manager' => [['ADM', 'jr_admission_manager']],
        'admission_counsellor' => [['ADM', 'admission_counsellor']],
        'admission_telecaller' => [['ADM', 'admission_telecaller']],
        'admission_officer' => [['ADM', 'admission_counsellor']],
        'dean_academics' => [['ACAD', 'department_owner']],
        'program_chair' => [['ACAD', 'department_manager']],
        'hod' => [['ACAD', 'department_supervisor']],
        'exam_cell' => [['EXAM', 'department_owner']],
        'accounts_officer' => [['ACC', 'department_owner']],
        'cmc' => [['CMC', 'department_owner']],
    ];

    public function applyApplicantVisibility($query, User $user, Department|string|null $department = 'ADM'): void
    {
        $this->applyAssignedUserVisibility($query, $user, $department, 'assigned_to', false);
    }

    public function applyLeadVisibility($query, User $user, Department|string|null $department = 'ADM'): void
    {
        $this->applyAssignedUserVisibility($query, $user, $department, 'assigned_to', true);
    }

    public function canViewAssignedUser(User $user, Department|string|null $department, ?int $assignedUserId, bool $includeUnassigned = false): bool
    {
        if ($user->hasRole('admin') || $this->canSeeAll($user, $department)) {
            return true;
        }

        if ($this->hasDepartmentRoleFallback($user, $department)
            && !$this->hasActiveMembers($department)) {
            return true;
        }

        if ($assignedUserId === null) {
            return $includeUnassigned || $this->hasDepartmentRoleFallback($user, $department);
        }

        return $this->visibleUserIds($user, $department)->contains($assignedUserId);
    }

    public function membershipFor(User $user, Department|string|null $department): ?DepartmentMember
    {
        $department = $this->resolveDepartment($department);
        if (!$department) {
            return null;
        }

        $this->ensureDefaultMembership($user, $department);

        return DepartmentMember::with(['role', 'team'])
            ->where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (DepartmentMember $member) => $member->role?->level ?? 999)
            ->first();
    }

    public function visibleUserIds(User $user, Department|string|null $department): Collection
    {
        if ($user->hasRole('admin')) {
            return collect();
        }

        $member = $this->membershipFor($user, $department);
        if (!$member) {
            return collect([$user->id]);
        }

        if ($member->role?->can_manage_lower_levels || $member->role?->can_view_team_data) {
            return $this->descendantMembers($member)
                ->pluck('user_id')
                ->push($user->id)
                ->unique()
                ->values();
        }

        return collect([$user->id]);
    }

    public function canSeeAll(User $user, Department|string|null $department): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $member = $this->membershipFor($user, $department);

        $permissions = collect($member?->role?->permissions ?? []);

        return (bool) ($member && (
            $permissions->contains('view_all')
            || ($member->role?->level <= 10 && $member->role?->can_view_team_data)
        ));
    }

    public function hasPermission(User $user, Department|string|null $department, string $permission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $member = $this->membershipFor($user, $department);
        $permissions = collect($member?->role?->permissions ?? []);

        return (bool) ($member && (
            $permissions->contains($permission)
            || ($permission === 'assign_work' && $member->role?->can_assign_work)
        ));
    }

    public function canAssignTo(User $actor, User|int $target, Department|string|null $department): bool
    {
        if ($actor->hasRole('admin') || $this->canSeeAll($actor, $department)) {
            return true;
        }

        if (!$this->hasPermission($actor, $department, 'assign_work')) {
            return false;
        }

        $targetId = $target instanceof User ? $target->id : $target;
        $visibleUserIds = $this->visibleUserIds($actor, $department);

        return $visibleUserIds->contains($targetId);
    }

    public function manageableMemberIds(User $actor, Department|string|null $department): Collection
    {
        if ($actor->hasRole('admin')) {
            $department = $this->resolveDepartment($department);

            return $department
                ? DepartmentMember::where('department_id', $department->id)->where('is_active', true)->pluck('id')
                : collect();
        }

        $member = $this->membershipFor($actor, $department);
        if (!$member || !$this->memberCanConfigureDepartment($member)) {
            return collect();
        }

        return $this->descendantMembers($member)
            ->pluck('id')
            ->push($member->id)
            ->unique()
            ->values();
    }

    public function canConfigureDepartmentHierarchy(User $actor, Department|string|null $department): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        $member = $this->membershipFor($actor, $department);

        return $member ? $this->memberCanConfigureDepartment($member) : false;
    }

    public function canManageRoleLevel(User $actor, Department|string|null $department, int $targetLevel): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        $member = $this->membershipFor($actor, $department);
        if (!$member && $this->hasDepartmentRoleFallback($actor, $department) && !$this->hasActiveMembers($department)) {
            return true;
        }

        if (!$member || !$this->memberCanConfigureDepartment($member)) {
            return false;
        }

        return $targetLevel > (int) ($member->role?->level ?? 999);
    }

    public function isAdmissionUser(User $user): bool
    {
        return $user->hasAnyRole(self::ADMISSION_ROLE_NAMES);
    }

    public function canApproveAdmission(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->hasPermission($user, 'ADM', 'approve_offers')
            || $this->hasPermission($user, 'ADM', 'configure_process')
            || $this->canSeeAll($user, 'ADM');
    }

    public function canVerifyAdmissionDocuments(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->hasPermission($user, 'ADM', 'verify_documents')
            || $this->hasPermission($user, 'ADM', 'follow_up')
            || $this->hasPermission($user, 'ADM', 'assign_work')
            || $this->canSeeAll($user, 'ADM');
    }

    public function admissionRoleMiddlewareList(): string
    {
        return implode('|', array_merge(self::ADMISSION_ROLE_NAMES, ['admin']));
    }

    public function manageableDepartments(User $user): Collection
    {
        if ($user->hasRole('admin')) {
            return Department::where('is_active', true)->orderBy('name')->get();
        }

        $this->ensureDefaultMemberships($user);

        return DepartmentMember::with(['department', 'role'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (DepartmentMember $member) => $this->memberCanConfigureDepartment($member))
            ->pluck('department')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function canManageDepartment(User $user, Department|string|null $department): bool
    {
        return $this->canConfigureDepartmentHierarchy($user, $department);
    }

    public function manageableGovernanceDepartments(User $user): Collection
    {
        if ($user->hasRole('admin')) {
            return Department::where('is_active', true)->orderBy('name')->get();
        }

        $this->ensureDefaultMemberships($user);

        return DepartmentMember::with(['department', 'role'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (DepartmentMember $member) => $this->memberCanAccessGovernance($member))
            ->pluck('department')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function canAccessDepartmentGovernance(User $user, Department|string|null $department): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $member = $this->membershipFor($user, $department);

        return $member ? $this->memberCanAccessGovernance($member) : false;
    }

    public function canManageDepartmentSettings(User $user, Department|string|null $department): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $member = $this->membershipFor($user, $department);

        return $member ? $this->memberCanManageDepartmentSettings($member) : false;
    }

    public function isFeatureEnabled(Department|string|null $department, string $featureKey, bool $default = true): bool
    {
        $department = $this->resolveDepartment($department);
        if (!$department) {
            return false;
        }

        $registeredFeature = $this->registeredFeature($department, $featureKey);
        if (!$registeredFeature) {
            return false;
        }

        $setting = DepartmentFeatureSetting::where('department_id', $department->id)
            ->where('feature_key', $featureKey)
            ->first();

        return $setting ? $setting->is_enabled : (bool) ($registeredFeature['default_enabled'] ?? $default);
    }

    public function registeredFeatures(Department|string|null $department): Collection
    {
        $department = $this->resolveDepartment($department);
        if (!$department) {
            return collect();
        }

        return collect(config("department_features.departments.{$department->code}", []))
            ->map(function (array $feature, string $key) {
                return [
                    'feature_key' => $key,
                    'feature_name' => $feature['name'] ?? str_replace('_', ' ', $key),
                    'category' => $feature['category'] ?? 'General',
                    'description' => $feature['description'] ?? null,
                    'default_enabled' => (bool) ($feature['default_enabled'] ?? true),
                    'is_registered' => true,
                ];
            })
            ->values();
    }

    public function registeredFeature(Department|string|null $department, string $featureKey): ?array
    {
        return $this->registeredFeatures($department)
            ->firstWhere('feature_key', $featureKey);
    }

    public function featureRows(Department|string|null $department): Collection
    {
        $department = $this->resolveDepartment($department);
        if (!$department) {
            return collect();
        }

        $settings = DepartmentFeatureSetting::where('department_id', $department->id)
            ->get()
            ->keyBy('feature_key');

        $registered = $this->registeredFeatures($department)
            ->map(function (array $feature) use ($settings) {
                $setting = $settings->get($feature['feature_key']);

                return (object) array_merge($feature, [
                    'feature_name' => $setting?->feature_name ?? $feature['feature_name'],
                    'is_enabled' => $setting ? $setting->is_enabled : $feature['default_enabled'],
                    'has_override' => (bool) $setting,
                    'updated_by' => $setting?->updated_by,
                ]);
            });

        return $registered
            ->sortBy([['category', 'asc'], ['feature_name', 'asc']])
            ->values();
    }

    public function setFeature(User $actor, Department|string|null $department, string $featureKey, string $featureName, bool $enabled): DepartmentFeatureSetting
    {
        $department = $this->resolveDepartment($department);
        abort_unless($department && $this->canManageDepartmentSettings($actor, $department), 403);
        $registeredFeature = $this->registeredFeature($department, $featureKey);
        abort_unless($registeredFeature, 422, 'Unknown department feature key.');
        $featureName = $registeredFeature['feature_name'];

        $setting = DepartmentFeatureSetting::updateOrCreate(
            ['department_id' => $department->id, 'feature_key' => $featureKey],
            [
                'feature_name' => $featureName,
                'is_enabled' => $enabled,
                'updated_by' => $actor->id,
            ]
        );

        $this->recordActivity($department, $actor, 'feature_setting_updated', "Feature {$featureName} " . ($enabled ? 'enabled' : 'disabled') . '.', $setting, null, [
            'feature_key' => $featureKey,
            'enabled' => $enabled,
        ]);

        return $setting;
    }

    public function impersonatableMembers(User $actor, Department|string|null $department): Collection
    {
        $department = $this->resolveDepartment($department);
        if (!$department) {
            return collect();
        }

        if ($actor->hasRole('admin')) {
            return DepartmentMember::with(['user', 'role', 'team'])
                ->where('department_id', $department->id)
                ->where('is_active', true)
                ->where('user_id', '!=', $actor->id)
                ->get()
                ->reject(fn (DepartmentMember $member) => $member->user?->hasRole('admin'))
                ->values();
        }

        $member = $this->membershipFor($actor, $department);
        if (!$member || !$this->canAccessDepartmentGovernance($actor, $department)) {
            return collect();
        }

        $descendantIds = $this->descendantMembers($member)->pluck('id');

        return DepartmentMember::with(['user', 'role', 'team'])
            ->whereIn('id', $descendantIds)
            ->get()
            ->reject(fn (DepartmentMember $child) => $child->user_id === $actor->id || $child->user?->hasRole('admin'))
            ->values();
    }

    public function canImpersonate(User $actor, User|int $target, Department|string|null $department): bool
    {
        $targetId = $target instanceof User ? $target->id : $target;

        if ($actor->id === $targetId) {
            return false;
        }

        return $this->impersonatableMembers($actor, $department)->contains('user_id', $targetId);
    }

    public function recordActivity(Department|string|null $department, ?User $actor, string $action, string $description, mixed $subject = null, ?User $target = null, array $metadata = []): void
    {
        $department = $this->resolveDepartment($department);
        if (!$department) {
            return;
        }

        try {
            $effectiveActor = $actor;
            $effectiveTarget = $target;
            $originalActorId = request()?->session()?->get('impersonation.original_user_id');
            if ($originalActorId && $actor && (int) $originalActorId !== (int) $actor->id) {
                $effectiveActor = User::find($originalActorId) ?: $actor;
                $effectiveTarget = $target ?: $actor;
                $metadata['impersonation'] = array_filter([
                    'session_id' => request()?->session()?->get('impersonation.session_id'),
                    'original_user_id' => (int) $originalActorId,
                    'impersonated_user_id' => $actor->id,
                    'target_user_id' => $target?->id,
                ], fn ($value) => $value !== null);
            }

            DepartmentActivityLog::create([
                'department_id' => $department->id,
                'actor_user_id' => $effectiveActor?->id,
                'target_user_id' => $effectiveTarget?->id,
                'action' => $action,
                'subject_type' => is_object($subject) ? get_class($subject) : null,
                'subject_id' => is_object($subject) ? ($subject->id ?? null) : null,
                'description' => $description,
                'metadata' => $metadata,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            // Department activity must not break the operational workflow.
        }
    }

    private function applyAssignedUserVisibility($query, User $user, Department|string|null $department, string $assignedColumn, bool $includeUnassigned): void
    {
        if ($user->hasRole('admin') || $this->canSeeAll($user, $department)) {
            return;
        }

        $member = $this->membershipFor($user, $department);
        if (!$member && $this->hasDepartmentRoleFallback($user, $department)
            && !$this->hasActiveMembers($department)) {
            return;
        }

        $visibleUserIds = $this->visibleUserIds($user, $department);

        $query->where(function ($scope) use ($assignedColumn, $visibleUserIds, $includeUnassigned) {
            $scope->whereIn($assignedColumn, $visibleUserIds);
            if ($includeUnassigned) {
                $scope->orWhereNull($assignedColumn);
            }
        });
    }

    private function hasActiveMembers(Department|string|null $department): bool
    {
        $department = $this->resolveDepartment($department);

        return $department
            ? DepartmentMember::where('department_id', $department->id)->where('is_active', true)->exists()
            : false;
    }

    private function hasDepartmentRoleFallback(User $user, Department|string|null $department): bool
    {
        $department = $this->resolveDepartment($department);

        if (!$department) {
            return false;
        }

        if ($department->code === 'ADM') {
            return $this->isAdmissionUser($user);
        }

        return collect($this->defaultMembershipMappingsFor($user))
            ->contains(fn (array $mapping) => $mapping[0] === $department->code);
    }

    private function memberCanAccessGovernance(DepartmentMember $member): bool
    {
        return $this->memberCanManageDepartmentSettings($member)
            || $this->memberCanConfigureDepartment($member)
            || (bool) ($member->role?->can_manage_lower_levels);
    }

    private function memberCanManageDepartmentSettings(DepartmentMember $member): bool
    {
        $permissions = collect($member->role?->permissions ?? []);

        return (bool) (
            $permissions->intersect(['manage_department_settings', 'configure_department'])->isNotEmpty()
            && (int) ($member->role?->level ?? 999) <= 10
        );
    }

    private function memberCanConfigureDepartment(DepartmentMember $member): bool
    {
        $permissions = collect($member->role?->permissions ?? []);

        return (bool) (
            $permissions->intersect(['manage_department_settings', 'configure_department', 'configure_process'])->isNotEmpty()
            && (int) ($member->role?->level ?? 999) <= 10
        );
    }

    private function ensureDefaultMemberships(User $user): void
    {
        foreach ($this->defaultMembershipMappingsFor($user) as [$departmentCode, $roleCode]) {
            $department = $this->resolveDepartment($departmentCode);
            if ($department) {
                $this->ensureDefaultMembership($user, $department, $roleCode);
            }
        }
    }

    private function ensureDefaultMembership(User $user, Department $department, ?string $preferredRoleCode = null): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if (DepartmentMember::where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists()) {
            return;
        }

        $roleCode = $preferredRoleCode;
        if (!$roleCode) {
            $mapping = collect($this->defaultMembershipMappingsFor($user))
                ->first(fn (array $mapping) => $mapping[0] === $department->code);
            $roleCode = $mapping[1] ?? null;
        }

        if (!$roleCode) {
            return;
        }

        $role = DepartmentRole::where('department_id', $department->id)
            ->where('code', $roleCode)
            ->where('is_active', true)
            ->first();

        if (!$role) {
            return;
        }

        DepartmentMember::firstOrCreate(
            [
                'department_id' => $department->id,
                'department_role_id' => $role->id,
                'user_id' => $user->id,
            ],
            ['is_active' => true]
        );
    }

    private function defaultMembershipMappingsFor(User $user): array
    {
        $mappings = [];

        foreach (self::ROLE_DEPARTMENT_MEMBERSHIPS as $roleName => $roleMappings) {
            if ($user->hasRole($roleName)) {
                array_push($mappings, ...$roleMappings);
            }
        }

        return $mappings;
    }

    public function descendantMembers(DepartmentMember $member): Collection
    {
        $seen = collect();
        $frontier = collect([$member->id]);

        while ($frontier->isNotEmpty()) {
            $children = DepartmentMember::whereIn('reports_to_member_id', $frontier)
                ->where('is_active', true)
                ->get();

            $newChildren = $children->reject(fn (DepartmentMember $child) => $seen->contains('id', $child->id));
            $seen = $seen->merge($newChildren);
            $frontier = $newChildren->pluck('id');
        }

        return $seen;
    }

    public function resolveDepartment(Department|string|null $department): ?Department
    {
        if ($department instanceof Department) {
            return $department;
        }

        if (!$department) {
            return null;
        }

        return Department::where('code', $department)
            ->orWhere('name', $department)
            ->first();
    }
}
