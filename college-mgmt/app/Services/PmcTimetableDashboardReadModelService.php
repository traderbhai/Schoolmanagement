<?php

namespace App\Services;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PmcTimetableDashboardReadModelService
{
    public const RESPONSIBILITY = 'PMC timetable dashboard KPIs, latest run, conflict, notification, and diagnostic read model.';

    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function dashboard(User $user, array $diagnostics): array
    {
        $scopedAllocationBatches = $this->applyScope(AcademicPmcCourseAllocationBatch::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedAllocations = $this->applyScope(AcademicPmcStudentCourseAllocation::query(), $user, [], [
            'term' => ['id' => 'term'],
            'student' => ['program_id' => 'program', 'batch_id' => 'batch'],
        ]);
        $scopedGroups = $this->applyScope(AcademicPmcCourseGroup::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedFacultyAssignments = $this->applyScope(AcademicPmcGroupFacultyAssignment::query(), $user, [], ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]);
        $scopedLocks = $this->applyScope(AcademicPmcLockedSlot::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedRuns = $this->applyScope(AcademicPmcTimetableGenerationRun::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedGenerationRunIds = (clone $scopedRuns)->pluck('id');
        $scopedConstraints = $this->generationScopedQuery(AcademicPmcTimetableConstraint::query(), $user, $scopedGenerationRunIds);
        $scopedQuality = $this->generationScopedQuery(AcademicPmcTimetableQualityScore::query(), $user, $scopedGenerationRunIds);

        return [
            'scopeLabel' => $this->policy->scopeLabel($user),
            'kpis' => [
                'allocation_batches' => $scopedAllocationBatches->count(),
                'student_allocations' => $scopedAllocations->count(),
                'course_groups' => $scopedGroups->count(),
                'faculty_assignments' => $scopedFacultyAssignments->count(),
                'locked_slots' => $scopedLocks->where('status', 'active')->count(),
                'hard_conflicts' => (clone $scopedConstraints)->where('severity', 'hard')->count(),
                'soft_warnings' => (clone $scopedConstraints)->where('severity', 'soft')->count(),
                'quality_score' => (int) round((clone $scopedQuality)->avg('overall_score') ?: 0),
            ],
            'readiness' => $diagnostics['readiness'],
            'launchControl' => $diagnostics['launch_control'],
            'basketDiagnostics' => $diagnostics['basket'],
            'allocationPressureDiagnostics' => $diagnostics['allocation_pressure'],
            'groupDiagnostics' => $diagnostics['group'],
            'facultyDiagnostics' => $diagnostics['faculty'],
            'facultySuitabilityDiagnostics' => $diagnostics['faculty_suitability'],
            'readinessInputDiagnostics' => $diagnostics['readiness_input'],
            'generationDiagnostics' => $diagnostics['generation'],
            'publishReadinessDiagnostics' => $diagnostics['publish_readiness'],
            'substitutionEmergencyDiagnostics' => $diagnostics['substitution_emergency'],
            'latestRun' => $scopedRuns->latest()->first(),
            'constraints' => $scopedConstraints->latest()->limit(8)->get(),
            'notifications' => AcademicPmcTimetableNotification::latest()->limit(8)->get(),
        ];
    }

    private function generationScopedQuery(Builder $query, User $user, $scopedGenerationRunIds): Builder
    {
        return $query->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedGenerationRunIds) {
            if ($scopedGenerationRunIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('generation_run_id', $scopedGenerationRunIds);
            }
        });
    }

    private function applyScope(Builder $query, User $user, array $directMap = [], array $relationMap = []): Builder
    {
        if ($this->policy->canIgnorePmcScope($user)) {
            return $query;
        }

        $scopes = [
            'program' => $this->policy->scopedProgramIds($user),
            'batch' => $this->policy->scopedBatchIds($user),
            'term' => $this->policy->scopedTermIds($user),
            'subject' => $this->policy->scopedSubjectIds($user),
        ];

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
}
