<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\User;

class PmcTimetableGenerationService
{
    public const RESPONSIBILITY = 'Canonical timetable demand, generation, placement, quality scoring, and solver alternatives.';

    public function generate(User $actor, array $data, callable $refreshConstraintsAndQuality, callable $audit): AcademicPmcTimetableGenerationRun
    {
        $run = app(TimetableOptimizationService::class)->solve($actor, [
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
        ], [
            'title' => $data['title'] ?? 'PMC Optimized Timetable',
            'strategy' => $data['strategy'] ?? 'balanced',
        ]);

        /** @var AcademicPmcTimetableQualityScore $quality */
        $quality = $refreshConstraintsAndQuality($run);
        AcademicPmcTimetableSolverAttempt::where('generation_run_id', $run->id)->latest()->first()?->update([
            'status' => $quality->hard_conflicts > 0 ? 'completed_with_conflicts' : ($run->unscheduled_count > 0 ? 'completed_with_unscheduled' : 'completed'),
            'hard_conflicts' => $quality->hard_conflicts,
            'soft_warnings' => $quality->soft_warnings,
            'quality_score' => $quality->overall_score,
        ]);

        $audit($actor, 'academic_pmc_v041_timetable_generated', $run->title, $run);

        return $run->fresh();
    }

    public function refreshPublishChecks(AcademicPmcTimetableGenerationRun $run, int $hard, int $soft, int $score, array $facultySuitabilityDiagnostics): void
    {
        AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)->delete();
        $facultySuitabilityBlockers = (int) $facultySuitabilityDiagnostics['blocker_total'];
        $checks = [
            ['hard_conflicts', $hard === 0 ? 'pass' : 'block', 'critical', 'Hard conflicts before publish', "{$hard} hard conflicts found.", 'pmc_head'],
            ['soft_warnings', $soft <= 3 ? 'pass' : 'warn', 'medium', 'Soft warning review', "{$soft} soft warnings need PMC review.", 'pmc_manager'],
            ['quality_score', $score >= 70 ? 'pass' : 'block', 'high', 'Timetable quality threshold', "Quality score is {$score}; minimum publish threshold is 70.", 'pmc_head'],
            ['faculty_suitability', $facultySuitabilityBlockers === 0 ? 'pass' : 'block', 'high', 'Faculty suitability before publish', $facultySuitabilityBlockers === 0 ? 'Faculty suitability blockers are clear.' : "{$facultySuitabilityBlockers} faculty suitability blocker(s) remain: expertise, adjunct availability, acknowledgement, overload, approval, or backup-only gaps.", 'pmc_head'],
            ['dean_after_freeze', 'warn', 'medium', 'Dean approval after freeze', 'Any post-freeze revision requires Dean approval.', 'dean_academics'],
        ];

        foreach ($checks as [$type, $status, $severity, $title, $description, $role]) {
            AcademicPmcTimetablePublishCheck::create([
                'generation_run_id' => $run->id,
                'check_type' => $type,
                'status' => $status,
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'required_role' => $role,
                'metadata' => $type === 'faculty_suitability' ? ['version' => 'PMC OS v0.089', 'diagnostics' => $facultySuitabilityDiagnostics] : null,
            ]);
        }
    }

    public function syncFacultySuitabilityPublishCheck(AcademicPmcTimetableGenerationRun $run, array $diagnostics): void
    {
        $blockers = (int) $diagnostics['blocker_total'];

        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $run->id, 'check_type' => 'faculty_suitability'],
            [
                'status' => $blockers === 0 ? 'pass' : 'block',
                'severity' => 'high',
                'title' => 'Faculty suitability before publish',
                'description' => $blockers === 0
                    ? 'Faculty suitability blockers are clear.'
                    : "{$blockers} faculty suitability blocker(s) remain: expertise, adjunct availability, acknowledgement, overload, approval, or backup-only gaps.",
                'required_role' => 'pmc_head',
                'metadata' => [
                    'version' => 'PMC OS v0.089',
                    'diagnostics' => $diagnostics,
                ],
            ]
        );
    }

    public function generationValidationDiagnostics(?User $user = null, ?callable $syncFacultySuitabilityPublishCheck = null): array
    {
        $latestRun = AcademicPmcTimetableGenerationRun::latest()->first();

        if (! $latestRun) {
            return [
                'has_run' => false,
                'latest_run_title' => 'No generation run yet',
                'latest_run_status' => 'missing',
                'scheduled_classes' => 0,
                'unscheduled_classes' => 0,
                'hard_conflicts' => 0,
                'soft_warnings' => 0,
                'quality_score' => 0,
                'quality_band' => 'missing',
                'solver_attempts' => 0,
                'failed_solver_attempts' => 0,
                'open_resolution_actions' => 0,
                'blocking_publish_checks' => 0,
                'impact_preview_records' => 0,
                'missing_impact_preview' => 1,
                'stale_input_sources' => 0,
                'ready_generations' => 0,
                'blocker_total' => 1,
                'status' => 'attention_required',
                'recommended_action' => 'Generate the timetable draft, validate constraints, and refresh the impact preview before publish.',
            ];
        }

        $runId = $latestRun->id;
        if ($syncFacultySuitabilityPublishCheck) {
            $syncFacultySuitabilityPublishCheck($latestRun);
        }
        $hardConflicts = AcademicPmcTimetableConstraint::where('generation_run_id', $runId)->where('severity', 'hard')->count();
        $softWarnings = AcademicPmcTimetableConstraint::where('generation_run_id', $runId)->where('severity', 'soft')->count();
        $openResolutionActions = AcademicPmcTimetableResolutionAction::where('generation_run_id', $runId)->whereNotIn('status', ['closed', 'done', 'cancelled'])->count();
        $blockingPublishChecks = AcademicPmcTimetablePublishCheck::where('generation_run_id', $runId)->whereIn('status', ['block', 'blocked', 'pending', 'open'])->count();
        $solverAttempts = AcademicPmcTimetableSolverAttempt::where('generation_run_id', $runId)->count();
        $failedSolverAttempts = AcademicPmcTimetableSolverAttempt::where('generation_run_id', $runId)->whereIn('status', ['failed', 'error', 'blocked'])->count();
        $impactPreviewRecords = AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $runId)->count();
        $staleInputSources = $this->staleGenerationInputSourceCount($latestRun);
        $qualityScore = (int) $latestRun->quality_score;
        $missingImpactPreview = ((int) $latestRun->scheduled_count > 0 && $impactPreviewRecords === 0) ? 1 : 0;

        $blockerTotal = (int) $latestRun->unscheduled_count
            + $hardConflicts
            + $openResolutionActions
            + $blockingPublishChecks
            + $failedSolverAttempts
            + $missingImpactPreview
            + $staleInputSources
            + ($qualityScore < 70 ? 1 : 0);

        return [
            'has_run' => true,
            'latest_run_title' => $latestRun->title,
            'latest_run_status' => $latestRun->status,
            'scheduled_classes' => (int) $latestRun->scheduled_count,
            'unscheduled_classes' => (int) $latestRun->unscheduled_count,
            'hard_conflicts' => $hardConflicts,
            'soft_warnings' => $softWarnings,
            'quality_score' => $qualityScore,
            'quality_band' => $qualityScore >= 85 ? 'strong' : ($qualityScore >= 70 ? 'publishable' : 'weak'),
            'solver_attempts' => $solverAttempts,
            'failed_solver_attempts' => $failedSolverAttempts,
            'open_resolution_actions' => $openResolutionActions,
            'blocking_publish_checks' => $blockingPublishChecks,
            'impact_preview_records' => $impactPreviewRecords,
            'missing_impact_preview' => $missingImpactPreview,
            'stale_input_sources' => $staleInputSources,
            'ready_generations' => $blockerTotal === 0 ? 1 : 0,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Generation is validated and ready for publish review.' : 'Resolve unscheduled classes, stale inputs, conflicts, publish checks, and missing impact preview before publishing.',
        ];
    }

    private function staleGenerationInputSourceCount(AcademicPmcTimetableGenerationRun $run): int
    {
        $updatedAt = $run->updated_at;

        return collect([
            AcademicPmcStudentCourseAllocation::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcCourseGroup::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcCourseGroupMember::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcGroupFacultyAssignment::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcFacultyPreference::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcLockedSlot::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcRoomReadinessReview::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcTimetableSessionDemand::where('updated_at', '>', $updatedAt)->exists(),
        ])->filter()->count();
    }
}
