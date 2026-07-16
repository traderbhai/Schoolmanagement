<?php

namespace App\Services;

use App\Models\User;

class PmcTimetableReadinessGateService
{
    public const RESPONSIBILITY = 'Launch, generation, publish, freeze, and operational readiness gates for canonical PMC timetable workflows.';

    public function readinessChecklist(callable $facultySuitabilityDiagnostics, callable $scopedExists, ?User $user = null): array
    {
        $facultySuitability = $facultySuitabilityDiagnostics(null, $user);

        return [
            ['label' => 'Student course allocation locked', 'ready' => $scopedExists('allocations', $user)],
            ['label' => 'Sections/groups created', 'ready' => $scopedExists('groups', $user)],
            ['label' => 'Faculty assigned to groups', 'ready' => $scopedExists('faculty_assignments', $user)],
            ['label' => 'Faculty suitability blockers cleared', 'ready' => (int) $facultySuitability['blocker_total'] === 0 && (int) $facultySuitability['total_assignments'] > 0],
            ['label' => 'Faculty availability/preferences captured', 'ready' => $scopedExists('faculty_preferences', $user)],
            ['label' => 'Rooms/labs and locked slots reviewed', 'ready' => $scopedExists('locked_slots', $user)],
            ['label' => 'Hard conflicts zero before publish', 'ready' => $scopedExists('no_hard_conflicts', $user)],
        ];
    }

    public function launchControl(array $diagnostics): array
    {
        $basketDiagnostics = $diagnostics['basket'];
        $groupDiagnostics = $diagnostics['group'];
        $facultyDiagnostics = $diagnostics['faculty'];
        $facultySuitabilityDiagnostics = $diagnostics['faculty_suitability'];
        $readinessInputDiagnostics = $diagnostics['readiness_inputs'];
        $generationDiagnostics = $diagnostics['generation'];
        $publishReadinessDiagnostics = $diagnostics['publish'];
        $facultyBlockers = (int) $facultyDiagnostics['blocker_total'] + (int) $facultySuitabilityDiagnostics['blocker_total'];

        $stages = [
            $this->launchStage('Course baskets', (int) $basketDiagnostics['ready_allocations'], (int) $basketDiagnostics['blocker_total'], 'Clear unapproved, waitlisted, overload, pending exception, and ungrouped basket blockers.', 'academics.pmc.student-course-baskets.index', ['status' => 'pending']),
            $this->launchStage('Sections and groups', (int) $groupDiagnostics['ready_groups'], (int) $groupDiagnostics['blocker_total'], 'Lock valid groups, resolve capacity issues, add missing faculty, and clear pending adjustments.', 'academics.pmc.course-groups.index', ['status' => 'active']),
            $this->launchStage('Faculty allocation', (int) min($facultyDiagnostics['ready_assignments'], $facultySuitabilityDiagnostics['suitable_assignments']), $facultyBlockers, 'Resolve missing primary/backup faculty, suitability/expertise gaps, adjunct-day constraints, acknowledgement concerns, availability gaps, and load-review blockers.', 'academics.pmc.section-faculty-allocation.index', ['status' => 'requested']),
            $this->launchStage('Readiness inputs', (int) $readinessInputDiagnostics['ready_inputs'], (int) $readinessInputDiagnostics['blocker_total'], 'Resolve incomplete preferences, invalid locked slots, hard-lock collisions, and room/lab readiness blockers.', 'academics.pmc.locked-slots.index', []),
            $this->launchStage('Generate and validate', (int) $generationDiagnostics['ready_generations'], (int) $generationDiagnostics['blocker_total'], 'Regenerate stale runs, schedule unscheduled classes, clear hard conflicts, close resolution actions, and refresh impact preview.', 'academics.pmc.timetable-generator.index', []),
            $this->launchStage('Publish and notify', (int) $publishReadinessDiagnostics['ready_versions'], (int) $publishReadinessDiagnostics['blocker_total'], 'Publish/freeze only after lifecycle workflow, impact, sync, revision, and notification blockers are clear.', 'academics.pmc.timetable-versions-v041.index', []),
        ];

        $readyStages = collect($stages)->where('status', 'ready')->count();
        $blockedStages = collect($stages)->where('status', 'blocked')->count();

        return [
            'status' => $blockedStages === 0 ? 'ready_to_launch' : 'attention_required',
            'ready_stages' => $readyStages,
            'total_stages' => count($stages),
            'blocked_stages' => $blockedStages,
            'next_action' => collect($stages)->firstWhere('status', 'blocked') ?: collect($stages)->last(),
            'stages' => $stages,
        ];
    }

    public function launchStage(string $label, int $doneCount, int $blockerCount, string $action, string $route, array $filters): array
    {
        return [
            'label' => $label,
            'done_count' => $doneCount,
            'blocker_count' => $blockerCount,
            'status' => $doneCount > 0 && $blockerCount === 0 ? 'ready' : 'blocked',
            'recommended_action' => $action,
            'route' => $route,
            'filters' => $filters,
        ];
    }
}
