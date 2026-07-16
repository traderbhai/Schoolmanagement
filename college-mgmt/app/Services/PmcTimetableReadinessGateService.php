<?php

namespace App\Services;

use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\TimetableVersion;
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

    public function publishFreezeReadinessDiagnostics(?User $user = null): array
    {
        $latestVersion = TimetableVersion::latest()->first();
        $latestWorkflow = $latestVersion
            ? AcademicPmcTimetableVersionWorkflow::where('timetable_version_id', $latestVersion->id)->latest()->first()
            : null;

        $publishedVersions = TimetableVersion::where('status', 'published')->count();
        $frozenVersions = TimetableVersion::where('status', 'frozen')->count()
            + AcademicPmcTimetableVersionWorkflow::where('lifecycle_status', 'frozen')->count();
        $blockingPublishChecks = AcademicPmcTimetablePublishCheck::whereIn('status', ['block', 'blocked', 'pending', 'open'])->count();
        $pendingChangeRequests = AcademicPmcTimetableChangeRequest::whereIn('status', ['requested', 'pending', 'open', 'revision_requested'])->count();
        $failedNotifications = AcademicPmcTimetableNotification::whereIn('status', ['failed', 'cancelled'])->count();
        $queuedNotifications = AcademicPmcTimetableNotification::whereIn('status', ['queued', 'pending'])->count();
        $rollbackWorkflows = AcademicPmcTimetableVersionWorkflow::where('lifecycle_status', 'like', '%rollback%')->count();
        $missingLifecycleWorkflow = $latestVersion && ! $latestWorkflow ? 1 : 0;
        $missingOfficialVersion = $publishedVersions + $frozenVersions === 0 ? 1 : 0;
        $operationalEntriesSynced = (int) data_get($latestWorkflow?->publish_summary, 'operational_entries_synced', 0);
        $impactRecords = (int) data_get($latestWorkflow?->publish_summary, 'impact_preview.impact_records', 0);
        $affectedStudents = (int) data_get($latestWorkflow?->impact_summary, 'affected_students', data_get($latestWorkflow?->publish_summary, 'impact_preview.affected_students', 0));
        $affectedFaculty = (int) data_get($latestWorkflow?->impact_summary, 'affected_faculty', data_get($latestWorkflow?->publish_summary, 'impact_preview.affected_faculty', 0));

        $blockerTotal = $missingOfficialVersion
            + $missingLifecycleWorkflow
            + $blockingPublishChecks
            + $pendingChangeRequests
            + $failedNotifications;

        return [
            'latest_version_label' => $latestVersion ? '#' . $latestVersion->version_number : 'No official version',
            'latest_version_status' => $latestVersion?->status ?? 'missing',
            'latest_lifecycle_status' => $latestWorkflow?->lifecycle_status ?? 'missing',
            'latest_approval_status' => $latestWorkflow?->approval_status ?? 'missing',
            'published_versions' => $publishedVersions,
            'frozen_versions' => $frozenVersions,
            'missing_official_version' => $missingOfficialVersion,
            'missing_lifecycle_workflow' => $missingLifecycleWorkflow,
            'blocking_publish_checks' => $blockingPublishChecks,
            'pending_change_requests' => $pendingChangeRequests,
            'failed_notifications' => $failedNotifications,
            'queued_notifications' => $queuedNotifications,
            'rollback_workflows' => $rollbackWorkflows,
            'operational_entries_synced' => $operationalEntriesSynced,
            'impact_records' => $impactRecords,
            'affected_students' => $affectedStudents,
            'affected_faculty' => $affectedFaculty,
            'ready_versions' => $blockerTotal === 0 ? max(1, $publishedVersions + $frozenVersions) : 0,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Official timetable lifecycle is ready for freeze/notification monitoring.' : 'Clear official version, lifecycle workflow, publish-check, revision, and failed-notification blockers before final freeze.',
        ];
    }

    public function substitutionEmergencyDiagnostics(?User $user = null): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $todayRecommendations = AcademicPmcSubstitutionRecommendation::whereDate('substitution_date', $today);
        $upcomingRecommendations = AcademicPmcSubstitutionRecommendation::whereBetween('substitution_date', [$today, $tomorrow]);
        $uncoveredToday = (clone $todayRecommendations)->where(function ($query) {
            $query->where('status', 'uncovered')->orWhereNull('substitute_teacher_id');
        })->count();
        $pendingRecommendations = (clone $upcomingRecommendations)->whereIn('status', ['recommended', 'pending', 'open'])->count();
        $lowScoreRecommendations = (clone $upcomingRecommendations)->where('score', '<', 60)->count();
        $failedSubstitutionNotices = AcademicPmcTimetableNotification::whereIn('notification_type', ['substitution', 'timetable_revision', 'cancellation'])
            ->whereIn('status', ['failed', 'cancelled'])
            ->count();
        $queuedSubstitutionNotices = AcademicPmcTimetableNotification::whereIn('notification_type', ['substitution', 'timetable_revision', 'cancellation'])
            ->whereIn('status', ['queued', 'pending'])
            ->count();
        $sameDayChangeRequests = AcademicPmcTimetableChangeRequest::whereIn('change_type', ['substitution', 'cancellation', 'reschedule', 'room_change', 'faculty_change'])
            ->whereIn('status', ['requested', 'pending', 'open', 'revision_requested'])
            ->count();
        $repeatedOriginalTeachers = AcademicPmcSubstitutionRecommendation::query()
            ->select('original_teacher_id')
            ->whereNotNull('original_teacher_id')
            ->whereDate('substitution_date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('original_teacher_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();
        $repeatedCourseGroups = AcademicPmcSubstitutionRecommendation::query()
            ->select('course_group_id')
            ->whereNotNull('course_group_id')
            ->whereDate('substitution_date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('course_group_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $blockerTotal = $uncoveredToday
            + $lowScoreRecommendations
            + $failedSubstitutionNotices
            + $sameDayChangeRequests;

        return [
            'today_recommendations' => (clone $todayRecommendations)->count(),
            'upcoming_recommendations' => (clone $upcomingRecommendations)->count(),
            'uncovered_today' => $uncoveredToday,
            'pending_recommendations' => $pendingRecommendations,
            'low_score_recommendations' => $lowScoreRecommendations,
            'failed_substitution_notices' => $failedSubstitutionNotices,
            'queued_substitution_notices' => $queuedSubstitutionNotices,
            'same_day_change_requests' => $sameDayChangeRequests,
            'repeated_original_teachers' => $repeatedOriginalTeachers,
            'repeated_course_groups' => $repeatedCourseGroups,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'No urgent substitution blockers for today/tomorrow.' : 'Resolve uncovered classes, weak recommendations, same-day changes, and failed substitution notifications before class time.',
        ];
    }
}
