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

    public function canWriteScope(User $user, array $context = [], ?AcademicPmcOperatingRecord $record = null): bool
    {
        if (! $this->canWrite($user, $record)) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics', 'pmc_head'])) {
            return true;
        }

        $checks = [
            'program' => $context['program_id'] ?? null,
            'batch' => $context['batch_id'] ?? null,
            'term' => $context['term_id'] ?? null,
            'subject' => $context['subject_id'] ?? null,
        ];

        $hasConcreteScope = false;
        foreach ($checks as $scopeType => $scopeId) {
            if ($scopeId === null || $scopeId === '') {
                continue;
            }
            $hasConcreteScope = true;
            if (! $this->scopes->canAccess($user, $scopeType, (int) $scopeId, null, true)) {
                return false;
            }
        }

        if ($hasConcreteScope) {
            return true;
        }

        if (! $record) {
            return false;
        }

        return $this->recordInWriteScope($user, $record);
    }

    public function canIgnorePmcScope(User $user): bool
    {
        if (! $this->hierarchy->canSeeAll($user) && ! $user->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics', 'pmc_head'])) {
            return false;
        }

        return true;
    }

    public function scopedProgramIds(User $user, bool $requireManage = false): ?array
    {
        return $this->scopedIds($user, 'program', $requireManage);
    }

    public function scopedBatchIds(User $user, bool $requireManage = false): ?array
    {
        return $this->scopedIds($user, 'batch', $requireManage);
    }

    public function scopedTermIds(User $user, bool $requireManage = false): ?array
    {
        return $this->scopedIds($user, 'term', $requireManage);
    }

    public function scopedSubjectIds(User $user, bool $requireManage = false): ?array
    {
        return $this->scopedIds($user, 'subject', $requireManage);
    }

    public function authorizeRead(User $user): void
    {
        abort_unless($this->canRead($user), 403);
    }

    public function authorizeWrite(User $user, ?AcademicPmcOperatingRecord $record = null): void
    {
        abort_unless($this->canWrite($user, $record), 403);
    }

    public function authorizeWriteScope(User $user, array $context = [], ?AcademicPmcOperatingRecord $record = null): void
    {
        abort_unless($this->canWriteScope($user, $context, $record), 403);
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

    private function recordInWriteScope(User $user, AcademicPmcOperatingRecord $record): bool
    {
        $checks = [
            'program' => $record->program_id,
            'batch' => $record->batch_id,
            'term' => $record->term_id,
            'subject' => $record->subject_id,
        ];

        $hasScopedRecord = false;
        foreach ($checks as $scopeType => $scopeId) {
            if ($scopeId === null || $scopeId === '') {
                continue;
            }

            $hasScopedRecord = true;
            if (! $this->scopes->canAccess($user, $scopeType, (int) $scopeId, null, true)) {
                return false;
            }
        }

        return $hasScopedRecord;
    }

    private function scopedIds(User $user, string $scopeType, bool $requireManage = false): ?array
    {
        if (
            $this->hierarchy->canSeeAll($user) ||
            $user->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics', 'pmc_head'])
        ) {
            return null;
        }

        return $this->scopes->scopedIdsFor($user, $scopeType, $requireManage);
    }
}
