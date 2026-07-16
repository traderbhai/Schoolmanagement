<?php

namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PmcTimetableScopeService
{
    public const RESPONSIBILITY = 'Apply PMC program, batch, term, and subject visibility scope to timetable queries.';

    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function applyScope(Builder $query, User $user, array $directMap = [], array $relationMap = []): Builder
    {
        if ($this->policy->canIgnorePmcScope($user)) {
            return $query;
        }

        $scopes = $this->scopeIds($user);

        foreach ($directMap as $column => $scopeType) {
            if (! array_key_exists($scopeType, $scopes)) {
                continue;
            }

            $ids = $scopes[$scopeType];
            if ($ids === null) {
                continue;
            }

            if (! is_array($ids) || empty($ids)) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn($column, $ids);
        }

        foreach ($relationMap as $relation => $mapping) {
            $query->whereHas($relation, function (Builder $relatedQuery) use ($mapping, $scopes): void {
                foreach ($mapping as $column => $scopeType) {
                    if (! array_key_exists($scopeType, $scopes)) {
                        continue;
                    }

                    $ids = $scopes[$scopeType];
                    if ($ids === null) {
                        continue;
                    }

                    if (! is_array($ids) || empty($ids)) {
                        $relatedQuery->whereRaw('1 = 0');
                        continue;
                    }

                    $relatedQuery->whereIn($column, $ids);
                }
            });
        }

        return $query;
    }

    public function scopedGenerationRunIdsByUser(User $user): Collection
    {
        if ($this->policy->canIgnorePmcScope($user)) {
            return AcademicPmcTimetableGenerationRun::query()->pluck('id');
        }

        $scopes = $this->applyScope(
            AcademicPmcTimetableGenerationRun::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        );

        return (clone $scopes)->pluck('id');
    }

    public function constrainConstraintsByUserScope(
        Builder $query,
        User $user,
        Builder $generationRunQuery,
        array $generationRunScopeMap = ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
    ): Builder {
        if ($this->policy->canIgnorePmcScope($user)) {
            return $query;
        }

        $runIds = (clone $this->applyScope($generationRunQuery, $user, $generationRunScopeMap))->pluck('id');
        if ($runIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('generation_run_id', $runIds);
    }

    public function scopeIds(User $user): array
    {
        return [
            'program' => $this->policy->scopedProgramIds($user),
            'batch' => $this->policy->scopedBatchIds($user),
            'term' => $this->policy->scopedTermIds($user),
            'subject' => $this->policy->scopedSubjectIds($user),
        ];
    }
}
