<?php
namespace App\Services;

use Spatie\Permission\Models\Role;
use App\Models\RoleFeatureAccess;

class RoleHierarchyService
{
    // Hierarchy: higher index = higher authority, inherits from all lower
    public const HIERARCHY = [
        'student'          => 0,
        'teacher'          => 1,
        'faculty'          => 1,
        'accounts_officer' => 2,
        'exam_cell'        => 2,
        'cmc'              => 2,
        'program_chair'    => 3,
        'hod'              => 3,
        'admission_officer'=> 3,
        'admission_head'   => 4,
        'dean_academics'   => 5,
        'admin'            => 6,
    ];

    /**
     * Get effective feature access level for a user considering role inheritance.
     * Higher-level roles inherit the access of all lower-level roles.
     */
    public function getEffectiveAccessLevel(array $userRoleNames, string $featureCode): ?string
    {
        $levels = RoleFeatureAccess::ACCESS_LEVELS;
        $bestIndex = -1;

        // Collect all roles the user has + all roles they inherit from (lower hierarchy)
        $effectiveRoleNames = $this->getInheritedRoles($userRoleNames);

        $roleIds = Role::whereIn('name', $effectiveRoleNames)->pluck('id')->toArray();

        RoleFeatureAccess::whereIn('role_id', $roleIds)
            ->where('feature_code', $featureCode)
            ->get()
            ->each(function ($record) use ($levels, &$bestIndex) {
                $idx = array_search($record->access_level, $levels);
                if ($idx !== false && $idx > $bestIndex) {
                    $bestIndex = $idx;
                }
            });

        return $bestIndex >= 0 ? $levels[$bestIndex] : null;
    }

    /**
     * Return all role names that the given roles inherit from (including themselves).
     */
    public function getInheritedRoles(array $roleNames): array
    {
        $maxLevel = -1;
        foreach ($roleNames as $name) {
            $level = self::HIERARCHY[$name] ?? -1;
            if ($level > $maxLevel) $maxLevel = $level;
        }

        if ($maxLevel < 0) return $roleNames;

        $inherited = [];
        foreach (self::HIERARCHY as $name => $level) {
            if ($level <= $maxLevel) {
                $inherited[] = $name;
            }
        }

        return array_unique(array_merge($roleNames, $inherited));
    }

    /**
     * Check if $roleName is higher in hierarchy than $thanRoleName.
     */
    public function isHigherThan(string $roleName, string $thanRoleName): bool
    {
        return (self::HIERARCHY[$roleName] ?? -1) > (self::HIERARCHY[$thanRoleName] ?? -1);
    }

    /**
     * Get sorted role tree for display.
     */
    public function getRoleTree(): array
    {
        return collect(self::HIERARCHY)
            ->sortByDesc(fn($v) => $v)
            ->map(fn($level, $name) => [
                'name'  => $name,
                'level' => $level,
                'role'  => Role::where('name', $name)->first(),
            ])
            ->values()
            ->toArray();
    }
}
