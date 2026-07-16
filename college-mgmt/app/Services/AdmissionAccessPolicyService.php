<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdmissionAccessPolicyService
{
    public function authorize(User $user, string $ability, ?Model $subject = null, ?string $reason = null): void
    {
        if (! $this->can($user, $ability, $subject)) {
            $this->audit($user, $ability, $subject, 'denied', $reason ?: 'Admission policy denied this action.');
            abort(403, 'You are not allowed to perform this admission action.');
        }

        $this->audit($user, $ability, $subject, 'allowed', $reason);
    }

    public function can(User $user, string $ability, ?Model $subject = null): bool
    {
        if ($user->hasAnyRole(['admin', 'admission_head', 'admission_director'])) {
            return true;
        }

        if (Str::startsWith($ability, 'accounts.')) {
            return $user->hasAnyRole(['accounts', 'admin']);
        }

        if ($user->hasAnyRole(['admission_manager', 'jr_admission_manager'])) {
            return $this->isOwnedOrAssigned($subject, $user) || $this->isTeamVisible($subject, $user) || in_array($ability, [
                'read.command_center', 'read.handoff', 'assessment.manage', 'seat.manage', 'communication.send',
            ], true);
        }

        if ($user->hasAnyRole(['admission_counsellor', 'admission_telecaller', 'admission_officer'])) {
            return Str::startsWith($ability, 'read.')
                ? $this->isOwnedOrAssigned($subject, $user)
                : $this->isOwnedOrAssigned($subject, $user) && ! Str::contains($ability, ['approve', 'override', 'release', 'withdraw']);
        }

        if ($user->hasRole('evaluator')) {
            return Str::startsWith($ability, 'assessment.') && $this->isEvaluatorAssigned($subject, $user);
        }

        if ($user->hasRole('applicant')) {
            return $subject instanceof Applicant && (int) $subject->user_id === (int) $user->id;
        }

        return false;
    }

    public function canSeeAll(User $user): bool
    {
        return $user->hasRole('admin') || app(DepartmentHierarchyService::class)->canSeeAll($user, 'ADM');
    }

    public function canApproveAdmission(User $user): bool
    {
        return app(DepartmentHierarchyService::class)->canApproveAdmission($user);
    }

    public function authorizeApproveAdmission(User $user): void
    {
        abort_unless($this->canApproveAdmission($user), 403);
    }

    public function canViewAssignedUser(User $user, ?int $assignedTo, bool $isLead = false): bool
    {
        return app(DepartmentHierarchyService::class)->canViewAssignedUser($user, 'ADM', $assignedTo, $isLead);
    }

    public function authorizeViewAssignedUser(User $user, ?int $assignedTo, bool $isLead = false): void
    {
        abort_unless($this->canViewAssignedUser($user, $assignedTo, $isLead), 403);
    }

    public function applyApplicantVisibility(Builder $query, User $user): Builder
    {
        app(DepartmentHierarchyService::class)->applyApplicantVisibility($query, $user, 'ADM');

        return $query;
    }

    public function evaluatorScope(User $user): ?User
    {
        return $this->canSeeAll($user) ? null : $user;
    }

    public function auditCoverage(): array
    {
        return collect(app('router')->getRoutes())
            ->filter(fn ($route) => Str::startsWith((string) $route->getName(), 'admission.'))
            ->map(function ($route) {
                $methods = implode('|', array_diff($route->methods(), ['HEAD']));
                $middleware = $route->gatherMiddleware();
                $isWrite = ! Str::contains($methods, 'GET');

                return [
                    'route_name' => $route->getName(),
                    'uri' => $route->uri(),
                    'method' => $methods,
                    'middleware' => implode(', ', $middleware),
                    'risk' => $isWrite ? 'write' : 'read',
                    'expected_scope' => $isWrite ? 'role plus admission policy' : 'role plus hierarchy visibility',
                    'missing_enforcement' => $isWrite && ! collect($middleware)->contains(fn ($m) => Str::contains($m, ['role:', 'department.feature'])),
                ];
            })
            ->values()
            ->all();
    }

    private function isOwnedOrAssigned(?Model $subject, User $user): bool
    {
        if (! $subject) {
            return false;
        }

        foreach (['assigned_to', 'current_handler_user_id', 'owner_user_id'] as $column) {
            if (isset($subject->{$column}) && (int) $subject->{$column} === (int) $user->id) {
                return true;
            }
        }

        return $subject instanceof Applicant && (int) $subject->user_id === (int) $user->id;
    }

    private function isTeamVisible(?Model $subject, User $user): bool
    {
        if (! $subject) {
            return false;
        }

        $ids = app(DepartmentHierarchyService::class)->visibleUserIds($user, 'ADM')->push($user->id)->unique();

        return collect(['assigned_to', 'current_handler_user_id', 'owner_user_id'])
            ->contains(fn ($column) => isset($subject->{$column}) && $ids->contains((int) $subject->{$column}));
    }

    private function isEvaluatorAssigned(?Model $subject, User $user): bool
    {
        if (! $subject) {
            return false;
        }

        return (isset($subject->evaluator_user_id) && (int) $subject->evaluator_user_id === (int) $user->id)
            || (isset($subject->user_id) && (int) $subject->user_id === (int) $user->id);
    }

    private function audit(User $user, string $ability, ?Model $subject, string $result, ?string $reason): void
    {
        DB::table('admission_policy_audit_logs')->insert([
            'route_name' => request()?->route()?->getName(),
            'ability' => $ability,
            'method' => request()?->method(),
            'actor_user_id' => $user->id,
            'actor_role' => $user->getRoleNames()->implode(','),
            'expected_scope' => 'admission hierarchy and role policy',
            'result' => $result,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'metadata' => json_encode(['v' => '0.039']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
