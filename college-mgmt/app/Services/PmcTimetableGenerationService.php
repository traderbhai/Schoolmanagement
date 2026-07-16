<?php

namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
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
}
