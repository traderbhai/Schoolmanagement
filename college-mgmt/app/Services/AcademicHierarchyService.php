<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\User;
use Illuminate\Support\Collection;

class AcademicHierarchyService
{
    public const DEPARTMENT_CODE = 'ACAD';

    public const BRANCHES = [
        'dean_office' => 'Dean Office',
        'pmc' => 'PMC',
        'coe_examination' => 'CoE / Examination',
        'iqac' => 'IQAC',
        'program_leadership' => 'Program Leadership',
    ];

    public const ROLE_CODES = [
        'academic_department_owner',
        'dean_academics',
        'pmc_head',
        'pmc_manager',
        'pmc_officer',
        'coe',
        'exam_manager',
        'exam_officer',
        'iqac_head',
        'iqac_manager',
        'iqac_officer',
        'program_director',
        'program_leader',
        'semester_coordinator',
        'course_coordinator',
        'faculty_mentor',
    ];

    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function department(): Department
    {
        return Department::where('code', self::DEPARTMENT_CODE)->firstOrFail();
    }

    public function membershipFor(User $user): ?DepartmentMember
    {
        return $this->hierarchy->membershipFor($user, self::DEPARTMENT_CODE);
    }

    public function canConfigure(User $user): bool
    {
        return $this->hierarchy->canConfigureDepartmentHierarchy($user, self::DEPARTMENT_CODE);
    }

    public function canSeeAll(User $user): bool
    {
        return $this->hierarchy->canSeeAll($user, self::DEPARTMENT_CODE);
    }

    public function visibleUserIds(User $user): Collection
    {
        return $this->hierarchy->visibleUserIds($user, self::DEPARTMENT_CODE);
    }

    public function branches(): Collection
    {
        return DepartmentTeam::where('department_id', $this->department()->id)
            ->where('is_active', true)
            ->whereIn('type', array_keys(self::BRANCHES))
            ->orderByRaw("case type when 'dean_office' then 1 when 'pmc' then 2 when 'coe_examination' then 3 when 'iqac' then 4 when 'program_leadership' then 5 else 6 end")
            ->orderBy('name')
            ->get();
    }

    public function roles(): Collection
    {
        return DepartmentRole::where('department_id', $this->department()->id)
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    public function members(): Collection
    {
        return DepartmentMember::with(['user', 'role', 'team', 'manager.user'])
            ->where('department_id', $this->department()->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (DepartmentMember $member) => sprintf('%03d-%s', $member->role?->level ?? 999, $member->user?->name ?? ''));
    }

    public function record(User $actor, string $action, string $description, mixed $subject = null, ?User $target = null, array $metadata = []): void
    {
        $this->hierarchy->recordActivity(self::DEPARTMENT_CODE, $actor, $action, $description, $subject, $target, $metadata);
    }
}
