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
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\User;

class PmcTimetableGenerationDiagnosticsService
{
    public const RESPONSIBILITY = 'Generation validation diagnostics and stale input detection for PMC timetable publish readiness.';

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
