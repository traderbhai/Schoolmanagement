<?php

namespace App\Services;

use App\Models\AcademicScopeAssignment;
use App\Models\DepartmentMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicScopeService
{
    public const SCOPE_TYPES = [
        'branch',
        'program',
        'batch',
        'term',
        'semester',
        'course',
        'subject',
        'cohort',
    ];

    public function __construct(private AcademicHierarchyService $academics) {}

    public function assign(
        User $actor,
        DepartmentMember $member,
        string $scopeType,
        ?int $scopeId,
        ?string $scopeCode,
        string $scopeName,
        ?string $context = null,
        bool $canManage = false,
        array $metadata = []
    ): AcademicScopeAssignment {
        abort_unless(in_array($scopeType, self::SCOPE_TYPES, true), 422, 'Unsupported academic scope type.');

        $assignment = AcademicScopeAssignment::updateOrCreate(
            [
                'department_member_id' => $member->id,
                'user_id' => $member->user_id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_code' => $scopeCode,
                'context' => $context,
            ],
            [
                'scope_name' => $scopeName,
                'can_manage' => $canManage,
                'is_active' => true,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'metadata' => $metadata,
            ]
        );

        $this->academics->record(
            $actor,
            'academic_scope_assigned',
            'Assigned ' . $scopeName . ' academic scope to ' . ($member->user?->name ?? 'member') . '.',
            $assignment,
            $member->user,
            ['scope_type' => $scopeType, 'scope_id' => $scopeId, 'scope_code' => $scopeCode, 'context' => $context]
        );

        return $assignment;
    }

    public function scopesFor(User $user, ?string $scopeType = null): Collection
    {
        $query = AcademicScopeAssignment::query()
            ->currentlyActive()
            ->where('user_id', $user->id)
            ->orderBy('scope_type')
            ->orderBy('scope_name');

        if ($scopeType) {
            $query->where('scope_type', $scopeType);
        }

        return $query->get();
    }

    public function scopeIdsFor(User $user, string $scopeType): Collection
    {
        return $this->scopesFor($user, $scopeType)
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function scopedIdsFor(User $user, string $scopeType, bool $requireManage = false): ?array
    {
        $baseQuery = AcademicScopeAssignment::query()
            ->currentlyActive()
            ->where('user_id', $user->id)
            ->where('scope_type', $scopeType);

        if ($requireManage) {
            $baseQuery->where('can_manage', true);
        }

        if ($baseQuery->clone()->whereNull('scope_id')->exists()) {
            return null;
        }

        $ids = $baseQuery->pluck('scope_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids ?: [];
    }

    public function canAccess(User $user, string $scopeType, int|string|null $scopeId = null, ?string $scopeCode = null, bool $write = false): bool
    {
        if ($this->academics->canSeeAll($user)) {
            return true;
        }

        $query = AcademicScopeAssignment::query()
            ->currentlyActive()
            ->where('user_id', $user->id)
            ->where('scope_type', $scopeType);

        if ($write) {
            $query->where('can_manage', true);
        }

        if ($scopeId !== null) {
            $query->where(function (Builder $scopeQuery) use ($scopeId) {
                $scopeQuery->where('scope_id', (int) $scopeId)->orWhereNull('scope_id');
            });
        }

        if ($scopeCode !== null) {
            $query->where(function (Builder $scopeQuery) use ($scopeCode) {
                $scopeQuery->where('scope_code', $scopeCode)->orWhereNull('scope_code');
            });
        }

        return $query->exists();
    }

    public function deactivate(User $actor, AcademicScopeAssignment $assignment): void
    {
        $assignment->update(['is_active' => false]);

        $this->academics->record(
            $actor,
            'academic_scope_deactivated',
            'Deactivated academic scope ' . $assignment->scope_name . '.',
            $assignment,
            $assignment->user,
            ['scope_type' => $assignment->scope_type]
        );
    }
}
