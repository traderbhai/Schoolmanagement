<?php

namespace App\Services;

use App\Models\User;

class AcademicAccessPolicyService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function canViewAcademics(User $user): bool
    {
        return $user->hasRole('admin')
            || (bool) $this->hierarchy->membershipFor($user)
            || $user->hasAnyRole([
                'dean_academics',
                'program_chair',
                'hod',
                'exam_cell',
                'academic_department_owner',
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
                'teacher',
                'faculty',
            ]);
    }

    public function canConfigureGovernance(User $user): bool
    {
        return $this->hierarchy->canConfigure($user);
    }

    public function canManageAcademicPlanning(User $user): bool
    {
        return $this->canConfigureGovernance($user)
            || $user->hasAnyRole(['admin', 'director', 'dean_academics', 'program_chair', 'hod']);
    }

    public function canReviewAcademicGovernance(User $user): bool
    {
        return $this->canConfigureGovernance($user)
            || $user->hasAnyRole(['admin', 'director', 'dean_academics']);
    }

    public function canUseDeanOperatingSystem(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);
    }

    public function canUseProgramLeadership(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'director',
            'academic_department_owner',
            'dean_academics',
            'pmc_head',
            'pmc_manager',
            'program_chair',
            'program_director',
            'program_leader',
            'hod',
            'semester_coordinator',
            'course_coordinator',
            'faculty_mentor',
        ]);
    }

    public function canUseCourseDelivery(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'director',
            'academic_department_owner',
            'dean_academics',
            'pmc_head',
            'pmc_manager',
            'pmc_officer',
            'program_chair',
            'program_director',
            'program_leader',
            'hod',
            'semester_coordinator',
            'course_coordinator',
            'faculty_mentor',
            'teacher',
            'faculty',
        ]);
    }

    public function canManageCurriculum(User $user): bool
    {
        return $this->canConfigureGovernance($user)
            || $user->hasAnyRole(['admin', 'director', 'dean_academics', 'program_chair', 'hod']);
    }

    public function canManageObe(User $user): bool
    {
        return $this->canConfigureGovernance($user)
            || $user->hasAnyRole(['admin', 'director', 'dean_academics', 'program_chair', 'hod', 'iqac_head', 'iqac_manager']);
    }

    public function canManageScholarships(User $user): bool
    {
        return $this->canConfigureGovernance($user)
            || $user->hasAnyRole(['admin', 'director', 'dean_academics', 'accounts_officer']);
    }

    public function canManageTermPromotions(User $user): bool
    {
        return $this->canConfigureGovernance($user)
            || $user->hasAnyRole(['admin', 'director', 'dean_academics', 'program_chair', 'hod']);
    }

    public function canManageScope(User $user, string $scopeType, int|string|null $scopeId = null, ?string $scopeCode = null): bool
    {
        return $this->hierarchy->canSeeAll($user)
            || $this->scopes->canAccess($user, $scopeType, $scopeId, $scopeCode, true);
    }

    public function canReadScope(User $user, string $scopeType, int|string|null $scopeId = null, ?string $scopeCode = null): bool
    {
        return $this->hierarchy->canSeeAll($user)
            || $this->scopes->canAccess($user, $scopeType, $scopeId, $scopeCode);
    }

    public function authorizeGovernance(User $user): void
    {
        abort_unless($this->canConfigureGovernance($user), 403);
    }

    public function authorizeAcademicPlanning(User $user): void
    {
        abort_unless($this->canManageAcademicPlanning($user), 403);
    }

    public function authorizeAcademicGovernanceReview(User $user): void
    {
        abort_unless($this->canReviewAcademicGovernance($user), 403);
    }

    public function authorizeDeanOperatingSystem(User $user): void
    {
        abort_unless($this->canUseDeanOperatingSystem($user), 403);
    }

    public function authorizeProgramLeadership(User $user): void
    {
        $this->authorizeRead($user);
        abort_unless($this->canUseProgramLeadership($user), 403);
    }

    public function authorizeCourseDelivery(User $user): void
    {
        $this->authorizeRead($user);
        abort_unless($this->canUseCourseDelivery($user), 403);
    }

    public function authorizeCurriculum(User $user): void
    {
        abort_unless($this->canManageCurriculum($user), 403);
    }

    public function authorizeObe(User $user): void
    {
        abort_unless($this->canManageObe($user), 403);
    }

    public function authorizeScholarships(User $user): void
    {
        abort_unless($this->canManageScholarships($user), 403);
    }

    public function authorizeTermPromotions(User $user): void
    {
        abort_unless($this->canManageTermPromotions($user), 403);
    }

    public function authorizeRead(User $user): void
    {
        abort_unless($this->canViewAcademics($user), 403);
    }
}
