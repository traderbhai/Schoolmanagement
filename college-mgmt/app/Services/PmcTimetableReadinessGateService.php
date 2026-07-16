<?php

namespace App\Services;

use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Support\Collection;

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

    public function readinessInputDiagnostics(callable $applyScope, ?User $user = null): array
    {
        $preferences = $applyScope(
            AcademicPmcFacultyPreference::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->get();
        $lockedSlots = $applyScope(
            AcademicPmcLockedSlot::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->where('status', 'active')->get();
        $roomReviews = $applyScope(
            AcademicPmcRoomReadinessReview::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->get();

        $incompletePreferences = $preferences->filter(function (AcademicPmcFacultyPreference $preference) {
            return empty($preference->available_days) && empty($preference->preferred_slots) && empty($preference->unavailable_slots);
        })->count();
        $restrictivePreferences = $preferences->filter(function (AcademicPmcFacultyPreference $preference) {
            return (int) $preference->max_classes_per_day <= 2 || (int) $preference->max_weekly_load <= 8 || ! empty($preference->unavailable_slots);
        })->count();

        $lockedMissingContext = $lockedSlots->filter(function (AcademicPmcLockedSlot $slot) {
            return ! $slot->timetable_slot_id || ($slot->slot_type === 'lab_block' && ! $slot->classroom_id) || ($slot->slot_type === 'faculty_fixed' && ! $slot->teacher_id);
        })->count();
        $hardLockCollisions = $this->hardLockCollisionCount($lockedSlots);

        $roomReviewBlockers = AcademicPmcRoomReadinessReview::whereIn('status', ['review_required', 'revision_required', 'rejected'])
            ->orWhereIn('readiness_band', ['blocked', 'warning'])
            ->count();
        $labNotReady = $roomReviews->filter(fn (AcademicPmcRoomReadinessReview $review) => $review->lab_required && ! $review->lab_ready)->count();
        $capacityExceptions = $roomReviews->filter(fn (AcademicPmcRoomReadinessReview $review) => ! $review->capacity_ok)->count();

        $blockerTotal = $incompletePreferences + $lockedMissingContext + $hardLockCollisions + $roomReviewBlockers + $labNotReady + $capacityExceptions;

        return [
            'total_preferences' => $preferences->count(),
            'complete_preferences' => $preferences->count() - $incompletePreferences,
            'incomplete_preferences' => $incompletePreferences,
            'restrictive_preferences' => $restrictivePreferences,
            'active_locked_slots' => $lockedSlots->count(),
            'hard_locked_slots' => $lockedSlots->where('is_hard_lock', true)->count(),
            'soft_locked_slots' => $lockedSlots->where('is_hard_lock', false)->count(),
            'locked_slots_missing_context' => $lockedMissingContext,
            'hard_lock_collisions' => $hardLockCollisions,
            'room_reviews' => $roomReviews->count(),
            'approved_room_reviews' => $roomReviews->whereIn('status', ['approved', 'approved_with_exception'])->count(),
            'room_review_blockers' => $roomReviewBlockers,
            'lab_not_ready' => $labNotReady,
            'capacity_exceptions' => $capacityExceptions,
            'ready_inputs' => $preferences->count() + $lockedSlots->count() + $roomReviews->whereIn('status', ['approved', 'approved_with_exception'])->count(),
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && ($preferences->isNotEmpty() || $lockedSlots->isNotEmpty() || $roomReviews->isNotEmpty()) ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Readiness inputs are ready for generation.' : 'Resolve preference, locked-slot, and room/lab readiness blockers before timetable generation.',
        ];
    }

    private function hardLockCollisionCount(Collection $lockedSlots): int
    {
        $hardLocks = $lockedSlots->where('is_hard_lock', true);
        $collisionKeys = collect();

        foreach (['teacher_id', 'classroom_id', 'course_group_id'] as $field) {
            $hardLocks
                ->filter(fn (AcademicPmcLockedSlot $slot) => filled($slot->{$field}))
                ->groupBy(fn (AcademicPmcLockedSlot $slot) => $field . ':' . $slot->day_of_week . ':' . $slot->timetable_slot_id . ':' . $slot->{$field})
                ->filter(fn (Collection $group) => $group->count() > 1)
                ->keys()
                ->each(fn ($key) => $collisionKeys->push($key));
        }

        return $collisionKeys->unique()->count();
    }

    public function facultyAllocationDiagnostics(callable $applyScope, callable $canIgnorePmcScope, ?User $user = null): array
    {
        $assignments = $applyScope(
            AcademicPmcGroupFacultyAssignment::with(['teacher', 'acknowledgements']),
            $user,
            [],
            ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )->get();
        $assignedGroupIds = $assignments->pluck('course_group_id')->filter()->unique();
        $groups = $applyScope(
            AcademicPmcCourseGroup::with('facultyAssignments'),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->get();
        $assignedTeacherIds = $assignments->pluck('teacher_id')->filter()->unique();
        $preferenceTeacherIds = $applyScope(
            AcademicPmcFacultyPreference::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )
            ->whereIn('teacher_id', $assignedTeacherIds)
            ->pluck('teacher_id')
            ->unique();

        $groupsMissingPrimary = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->facultyAssignments->whereIn('assignment_role', ['primary', 'co_faculty', 'lab_faculty', 'tutorial_faculty'])->isEmpty())->count();
        $groupsWithoutBackup = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->facultyAssignments->where('is_backup', true)->isEmpty())->count();
        $scopedAssignmentIds = $assignments->pluck('id')->filter()->values();
        $pendingAcknowledgements = $user && ! $canIgnorePmcScope($user)
            ? AcademicPmcFacultyAssignmentAcknowledgement::whereIn('group_faculty_assignment_id', $scopedAssignmentIds)->whereIn(
                'status',
                ['pending', 'requested', 'concern_raised', 'declined', 'revision_required']
            )->count()
            : AcademicPmcFacultyAssignmentAcknowledgement::whereIn(
                'status',
                ['pending', 'requested', 'concern_raised', 'declined', 'revision_required']
            )->count();
        $assignmentsWithoutAcknowledgement = $assignments->filter(fn (AcademicPmcGroupFacultyAssignment $assignment) => $assignment->acknowledgements->isEmpty())->count();
        $teachersMissingPreference = $assignedTeacherIds->diff($preferenceTeacherIds)->count();
        $unapprovedAssignments = $assignments->whereNotIn('approval_status', ['pmc_approved', 'faculty_accepted', 'accepted', 'approved'])->count();
        $scopedTeacherIds = $assignments->pluck('teacher_id')->filter()->values();
        $loadReviewBlockers = AcademicPmcFacultyLoadReview::query()
            ->when($user && ! $canIgnorePmcScope($user), function ($query) use ($scopedTeacherIds) {
                if ($scopedTeacherIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereIn('teacher_id', $scopedTeacherIds);
            })
            ->whereIn('status', ['review_required', 'approval_required', 'revision_required'])
            ->orWhere(fn ($query) => $query->whereIn('load_band', ['overload', 'critical'])->whereNotIn('status', ['approved_overload', 'approved']))
            ->count();
        $overloadReviews = AcademicPmcFacultyLoadReview::query()
            ->when($user && ! $canIgnorePmcScope($user), function ($query) use ($scopedTeacherIds) {
                if ($scopedTeacherIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereIn('teacher_id', $scopedTeacherIds);
            })
            ->whereIn('load_band', ['overload', 'critical'])->count();
        $blockerTotal = $groupsMissingPrimary + $pendingAcknowledgements + $assignmentsWithoutAcknowledgement + $teachersMissingPreference + $unapprovedAssignments + $loadReviewBlockers;

        return [
            'total_assignments' => $assignments->count(),
            'ready_assignments' => $assignments->filter(fn (AcademicPmcGroupFacultyAssignment $assignment) => in_array($assignment->approval_status, ['pmc_approved', 'faculty_accepted', 'accepted', 'approved'], true))->count(),
            'assigned_groups' => $assignedGroupIds->count(),
            'groups_missing_primary' => $groupsMissingPrimary,
            'groups_without_backup' => $groupsWithoutBackup,
            'pending_acknowledgements' => $pendingAcknowledgements,
            'assignments_without_acknowledgement' => $assignmentsWithoutAcknowledgement,
            'teachers_missing_preference' => $teachersMissingPreference,
            'unapproved_assignments' => $unapprovedAssignments,
            'load_review_blockers' => $loadReviewBlockers,
            'overload_reviews' => $overloadReviews,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $assignments->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Faculty allocation is ready for timetable generation.' : 'Resolve faculty assignment, acknowledgement, preference, and load-review blockers before timetable generation.',
        ];
    }

    public function facultySuitabilityDiagnostics(callable $applyScope, ?AcademicPmcTimetableGenerationRun $run = null, ?User $user = null): array
    {
        $courseGroupIds = $run
            ? AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->pluck('course_group_id')->filter()->unique()->values()
            : collect();
        $assignments = AcademicPmcGroupFacultyAssignment::with(['courseGroup.subject', 'teacher.user', 'acknowledgements'])
            ->when($run && $courseGroupIds->isNotEmpty(), fn ($query) => $query->whereIn('course_group_id', $courseGroupIds))
            ->when($run && $courseGroupIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $run && $user, function ($query) use ($user, $applyScope) {
                return $applyScope(
                    $query,
                    $user,
                    [],
                    ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
                );
            })
            ->get();
        $teacherIds = $assignments->pluck('teacher_id')->filter()->unique();
        $preferences = AcademicPmcFacultyPreference::whereIn('teacher_id', $teacherIds)->get()->groupBy('teacher_id');
        $loadReviews = AcademicPmcFacultyLoadReview::whereIn('teacher_id', $teacherIds)->latest()->get()->groupBy('teacher_id');

        $missingExpertise = 0;
        $adjunctDayRisk = 0;
        $restrictionRisks = 0;
        $ackConcernRisks = 0;
        $declinedAssignments = 0;
        $overloadRisks = 0;
        $unapprovedSuitability = 0;
        $backupOnlyPrimaryGap = 0;
        $suitableAssignments = 0;

        foreach ($assignments as $assignment) {
            $teacherPreferences = $preferences->get($assignment->teacher_id, collect());
            $preference = $teacherPreferences->firstWhere('term_id', $assignment->courseGroup?->term_id)
                ?: $teacherPreferences->firstWhere('term_id', null);
            $teacherLoadReviews = $loadReviews->get($assignment->teacher_id, collect());
            $latestLoad = ($run ? $teacherLoadReviews->firstWhere('generation_run_id', $run->id) : null)
                ?: $teacherLoadReviews->firstWhere('term_id', $assignment->courseGroup?->term_id)
                ?: $teacherLoadReviews->firstWhere('term_id', null);
            $subjectId = $assignment->courseGroup?->subject_id;
            $expertise = collect($preference?->subject_expertise ?? [])->map(fn ($value) => (string) $value);
            $hasExpertise = ! $subjectId || $expertise->isEmpty() || $expertise->contains((string) $subjectId) || $expertise->contains($assignment->courseGroup?->subject?->code);
            $isAdjunct = in_array($preference?->faculty_type, ['adjunct', 'visiting', 'guest'], true);
            $hasAllowedDays = ! $isAdjunct || ! empty($preference?->available_days);
            $hasRestriction = filled($preference?->restriction_notes) || ! empty($preference?->unavailable_slots);
            $ackStatuses = $assignment->acknowledgements->pluck('status')->merge($assignment->acknowledgements->pluck('response_type'))->filter();
            $hasConcern = $ackStatuses->intersect(['concern_raised', 'decline', 'declined', 'revision_required'])->isNotEmpty();
            $isDeclined = $ackStatuses->intersect(['decline', 'declined'])->isNotEmpty();
            $loadBlocked = $latestLoad && (in_array($latestLoad->load_band, ['overload', 'critical'], true) || in_array($latestLoad->status, ['approval_required', 'revision_required'], true));
            $approved = in_array($assignment->approval_status, ['pmc_approved', 'faculty_accepted', 'accepted', 'approved'], true);
            $primaryGap = $assignment->is_backup && ! AcademicPmcGroupFacultyAssignment::where('course_group_id', $assignment->course_group_id)
                ->whereIn('assignment_role', ['primary', 'co_faculty', 'lab_faculty', 'tutorial_faculty'])
                ->exists();

            $missingExpertise += $hasExpertise ? 0 : 1;
            $adjunctDayRisk += $hasAllowedDays ? 0 : 1;
            $restrictionRisks += $hasRestriction ? 1 : 0;
            $ackConcernRisks += $hasConcern ? 1 : 0;
            $declinedAssignments += $isDeclined ? 1 : 0;
            $overloadRisks += $loadBlocked ? 1 : 0;
            $unapprovedSuitability += $approved ? 0 : 1;
            $backupOnlyPrimaryGap += $primaryGap ? 1 : 0;

            if ($hasExpertise && $hasAllowedDays && ! $hasConcern && ! $loadBlocked && $approved && ! $primaryGap) {
                $suitableAssignments++;
            }
        }

        $blockerTotal = $missingExpertise
            + $adjunctDayRisk
            + $ackConcernRisks
            + $declinedAssignments
            + $overloadRisks
            + $unapprovedSuitability
            + $backupOnlyPrimaryGap;

        return [
            'total_assignments' => $assignments->count(),
            'suitable_assignments' => $suitableAssignments,
            'missing_expertise' => $missingExpertise,
            'adjunct_day_risk' => $adjunctDayRisk,
            'restriction_risks' => $restrictionRisks,
            'acknowledgement_concerns' => $ackConcernRisks,
            'declined_assignments' => $declinedAssignments,
            'overload_risks' => $overloadRisks,
            'unapproved_suitability' => $unapprovedSuitability,
            'backup_only_primary_gap' => $backupOnlyPrimaryGap,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $assignments->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0
                ? 'Faculty suitability is ready for timetable generation.'
                : 'Resolve subject expertise gaps, adjunct-day constraints, acknowledgement concerns, overload approvals, and backup-only primary gaps before final timetable generation.',
        ];
    }

    public function courseBasketDiagnostics(callable $applyScope, callable $canIgnorePmcScope, ?User $user = null): array
    {
        $allocations = AcademicPmcStudentCourseAllocation::with(['subject', 'allocationBatch', 'groupMemberships'])
            ->when($user && ! $canIgnorePmcScope($user), function ($query) use ($user, $applyScope) {
                return $applyScope(
                    $query,
                    $user,
                    [],
                    ['student' => ['program_id' => 'program', 'batch_id' => 'batch']]
                );
            })
            ->whereNotIn('basket_status', ['dropped', 'withdrawn'])
            ->get();

        $ungrouped = $allocations->filter(fn (AcademicPmcStudentCourseAllocation $allocation) => $allocation->groupMemberships->where('status', 'active')->isEmpty())->count();
        $unapproved = $allocations->whereNotIn('basket_status', ['approved', 'locked', 'allocated'])->count();
        $waitlisted = $allocations->where('waitlisted', true)->count();
        $flagged = $allocations->filter(fn (AcademicPmcStudentCourseAllocation $allocation) => filled($allocation->validation_flags))->count();
        $pendingExceptions = AcademicPmcCourseAllocationException::whereIn('status', ['requested', 'pending', 'under_review'])->count();

        $creditOverload = $allocations
            ->groupBy(fn (AcademicPmcStudentCourseAllocation $allocation) => ($allocation->student_id ?: 'missing') . '-' . ($allocation->term_id ?: 'missing'))
            ->filter(function (Collection $studentTermAllocations) {
                $credits = (int) $studentTermAllocations->sum(fn (AcademicPmcStudentCourseAllocation $allocation) => (int) ($allocation->subject?->credits ?? 0));
                $maxCredits = (int) data_get($studentTermAllocations->first()?->allocationBatch?->rules, 'max_credits', 30);

                return $credits > $maxCredits;
            })
            ->count();

        $blockerTotal = $unapproved + $waitlisted + $flagged + $pendingExceptions + $ungrouped + $creditOverload;

        return [
            'total_allocations' => $allocations->count(),
            'ready_allocations' => $allocations->whereIn('basket_status', ['approved', 'locked', 'allocated'])->count(),
            'unapproved_allocations' => $unapproved,
            'waitlisted_allocations' => $waitlisted,
            'flagged_allocations' => $flagged,
            'pending_exceptions' => $pendingExceptions,
            'ungrouped_allocations' => $ungrouped,
            'credit_overload_baskets' => $creditOverload,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $allocations->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Course baskets are ready for group locking.' : 'Review basket blockers before section/group locking.',
        ];
    }
}
