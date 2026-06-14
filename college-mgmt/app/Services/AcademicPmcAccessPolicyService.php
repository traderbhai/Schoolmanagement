<?php

namespace App\Services;

use App\Models\AcademicPmcOperatingRecord;
use App\Models\User;

class AcademicPmcAccessPolicyService
{
    public function __construct(private AcademicAccessPolicyService $academics, private AcademicHierarchyService $hierarchy, private AcademicScopeService $scopes) {}

    public function canRead(User $user): bool
    {
        return $this->academics->canViewAcademics($user) && $user->hasAnyRole($this->readRoles());
    }

    public function canWrite(User $user, ?AcademicPmcOperatingRecord $record = null): bool
    {
        if (! $this->canRead($user)) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics', 'pmc_head'])) {
            return true;
        }

        if ($record && $record->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasAnyRole(['pmc_manager', 'pmc_officer', 'program_chair', 'program_director', 'program_leader']);
    }

    public function authorizeRead(User $user): void
    {
        abort_unless($this->canRead($user), 403);
    }

    public function authorizeWrite(User $user, ?AcademicPmcOperatingRecord $record = null): void
    {
        abort_unless($this->canWrite($user, $record), 403);
    }

    public function scopeLabel(User $user): string
    {
        if ($this->hierarchy->canSeeAll($user) || $user->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics', 'pmc_head'])) {
            return 'Full PMC operating scope';
        }

        $scopes = $this->scopes->scopesFor($user);
        return $scopes->take(3)->pluck('scope_name')->filter()->join(', ') ?: 'Assigned PMC scope';
    }

    private function readRoles(): array
    {
        return [
            'admin', 'director', 'academic_department_owner', 'dean_academics',
            'pmc_head', 'pmc_manager', 'pmc_officer', 'program_chair',
            'program_director', 'program_leader', 'hod', 'semester_coordinator',
            'course_coordinator', 'faculty_mentor',
        ];
    }
}
