<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Teacher;
use App\Models\User;

class PmcTimetableRevisionService
{
    public function __construct(private PmcTimetableReadModelService $readModels) {}

    public function createResolutionAction(User $actor, AcademicPmcTimetableConstraint $constraint, array $data): AcademicPmcTimetableResolutionAction
    {
        $actionType = $data['action_type'] ?? $this->defaultResolutionActionType($constraint);
        $action = AcademicPmcTimetableResolutionAction::updateOrCreate(
            ['constraint_id' => $constraint->id, 'action_type' => $actionType],
            [
                'generation_run_id' => $constraint->generation_run_id,
                'title' => $data['title'] ?? 'Resolve ' . $constraint->title,
                'description' => $data['description'] ?? $constraint->recommended_fix,
                'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
                'assigned_by' => $actor->id,
                'priority' => $data['priority'] ?? ($constraint->severity === 'hard' ? 'high' : 'normal'),
                'status' => 'open',
                'due_at' => $data['due_at'] ?? now()->addDays($constraint->severity === 'hard' ? 1 : 3),
                'metadata' => [
                    'constraint_type' => $constraint->constraint_type,
                    'affected_type' => $constraint->affected_type,
                    'affected_key' => $constraint->affected_key,
                    'source_route' => $constraint->source_route,
                ],
            ]
        );

        $this->audit($actor, 'academic_pmc_v045_resolution_action_created', $action->title, $action, ['constraint_id' => $constraint->id]);

        return $action->fresh();
    }

    public function closeResolutionAction(User $actor, AcademicPmcTimetableResolutionAction $action, array $data): AcademicPmcTimetableResolutionAction
    {
        if (empty($data['resolution_note'])) {
            abort(422, 'Resolution note is required.');
        }

        $action->update([
            'status' => $data['status'] ?? 'resolved',
            'resolution_note' => $data['resolution_note'],
            'evidence' => $data['evidence'] ?? ['method' => 'manual_review', 'actor_user_id' => $actor->id],
            'closed_at' => now(),
        ]);

        $openActions = AcademicPmcTimetableResolutionAction::where('generation_run_id', $action->generation_run_id)
            ->whereIn('status', ['open', 'in_progress', 'blocked'])
            ->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $action->generation_run_id, 'check_type' => 'resolution_actions'],
            [
                'status' => $openActions === 0 ? 'pass' : 'block',
                'severity' => $openActions === 0 ? 'info' : 'high',
                'title' => 'Open conflict resolution actions',
                'description' => "{$openActions} resolution action(s) remain open.",
                'required_role' => 'pmc_head',
            ]
        );

        $this->audit($actor, 'academic_pmc_v045_resolution_action_closed', $action->title, $action);

        return $action->fresh();
    }

    public function requestChange(User $actor, array $data): AcademicPmcTimetableChangeRequest
    {
        $targetItem = null;
        if (! empty($data['pmc_generation_item_id'])) {
            $targetItem = $this->readModels->officialTimetableItemsQuery()
                ->with(['courseGroup.members', 'classroom', 'teacher.user', 'slot'])
                ->findOrFail($data['pmc_generation_item_id']);
            $data['timetable_version_id'] = $targetItem->timetable_version_id;
        }

        $change = AcademicPmcTimetableChangeRequest::create($data + ['requested_by' => $actor->id, 'status' => 'requested']);

        $targetItem
            ? $this->createSessionChangeImpactRecords($change, $targetItem)
            : $this->createGeneralChangeImpactRecords($change);

        $this->audit($actor, 'academic_pmc_v041_change_requested', $change->reason ?: 'Timetable change requested', $change);

        return $change;
    }

    public function decideChange(User $actor, AcademicPmcTimetableChangeRequest $change, string $status, ?string $note): AcademicPmcTimetableChangeRequest
    {
        if (! in_array($change->status, ['requested', 'pending', 'open', 'revision_requested'], true)) {
            abort(422, 'Reviewed timetable change decisions are locked. Create a new change request for further revision.');
        }

        if (in_array($status, ['rejected', 'revision_requested'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $change->update(['status' => $status, 'decided_by' => $actor->id, 'decision_note' => $note]);
        $this->audit($actor, 'academic_pmc_v041_change_decided', $change->change_type, $change);

        return $change->fresh();
    }

    public function recommendSubstitution(User $actor, array $data): AcademicPmcSubstitutionRecommendation
    {
        $original = Teacher::find($data['original_teacher_id'] ?? null);
        $courseGroup = AcademicPmcCourseGroup::with('subject')->find($data['course_group_id'] ?? null);
        $targetDate = \Carbon\Carbon::parse($data['substitution_date'] ?? now()->toDateString());
        $targetItem = $courseGroup
            ? $this->readModels->officialTimetableItemsQuery()
                ->where('course_group_id', $courseGroup->id)
                ->where('teacher_id', $original?->id)
                ->where('status', 'scheduled')
                ->orderByDesc('id')
                ->first()
            : null;
        $dayOfWeek = $targetItem?->day_of_week ?: $targetDate->dayOfWeekIso;
        $slotId = $targetItem?->timetable_slot_id;

        $ranked = Teacher::with('user')
            ->where('id', '!=', $original?->id)
            ->where('status', 'active')
            ->get()
            ->map(fn (Teacher $candidate) => $this->scoreSubstitutionCandidate($candidate, $courseGroup, $dayOfWeek, $slotId))
            ->sortByDesc('score')
            ->values();

        $best = $ranked->first();
        $substitute = $best && ($best['score'] ?? 0) > 0 ? Teacher::find($best['teacher_id']) : null;
        $recommendation = AcademicPmcSubstitutionRecommendation::create([
            'pmc_generation_item_id' => $targetItem?->id,
            'timetable_entry_id' => $targetItem?->operational_timetable_entry_id,
            'course_group_id' => $data['course_group_id'] ?? null,
            'original_teacher_id' => $original?->id,
            'substitute_teacher_id' => $substitute?->id,
            'substitution_date' => $data['substitution_date'] ?? now()->toDateString(),
            'status' => $substitute ? 'recommended' : 'uncovered',
            'score' => $best['score'] ?? 0,
            'reasons' => $substitute ? ($best['reasons'] ?? []) : ['no_conflict_free_substitute_found'],
            'conflict_checks' => [
                'target_day' => $dayOfWeek,
                'target_slot_id' => $slotId,
                'faculty' => $best['conflict_checks']['faculty'] ?? 'uncovered',
                'availability' => $best['conflict_checks']['availability'] ?? 'uncovered',
                'workload' => $best['conflict_checks']['workload'] ?? 'uncovered',
                'ranked_candidates' => $ranked->take(5)->map(fn ($row) => [
                    'teacher_id' => $row['teacher_id'],
                    'teacher_name' => $row['teacher_name'],
                    'score' => $row['score'],
                    'reasons' => $row['reasons'],
                    'conflict_checks' => $row['conflict_checks'],
                ])->values()->all(),
            ],
        ]);

        $this->audit($actor, 'academic_pmc_v041_substitution_recommended', 'Substitution recommendation created', $recommendation);

        return $recommendation;
    }

    public function logNotification(User $actor, array $data): AcademicPmcTimetableNotification
    {
        $notification = AcademicPmcTimetableNotification::create($data + ['status' => 'queued']);
        $this->audit($actor, 'academic_pmc_v041_notification_logged', $notification->title, $notification);

        return $notification;
    }

    public function updateNotificationStatus(User $actor, AcademicPmcTimetableNotification $notification, string $status, ?string $note = null): AcademicPmcTimetableNotification
    {
        abort_unless(in_array($status, ['queued', 'sent', 'read', 'failed', 'cancelled'], true), 422, 'Invalid notification status.');
        if (in_array($status, ['failed', 'cancelled'], true) && ! filled($note)) {
            abort(422, 'Failure or cancellation note is required.');
        }

        $metadata = $notification->metadata ?: [];
        $history = $metadata['status_history'] ?? [];
        $history[] = [
            'from' => $notification->status,
            'to' => $status,
            'note' => $note,
            'actor_user_id' => $actor->id,
            'changed_at' => now()->toDateTimeString(),
        ];

        $notification->update([
            'status' => $status,
            'metadata' => array_merge($metadata, [
                'version' => $metadata['version'] ?? 'PMC OS v0.074',
                'latest_status_note' => $note,
                'latest_status_actor_id' => $actor->id,
                'latest_status_changed_at' => now()->toDateTimeString(),
                'status_history' => $history,
            ]),
        ]);

        $this->audit($actor, 'academic_pmc_v074_notification_status_updated', 'PMC timetable notification status updated to ' . $status, $notification, [
            'notification_id' => $notification->id,
            'status' => $status,
            'note' => $note,
        ]);

        return $notification->fresh();
    }

    public function retryNotification(User $actor, AcademicPmcTimetableNotification $notification, ?string $note = null): AcademicPmcTimetableNotification
    {
        abort_unless(in_array($notification->status, ['failed', 'cancelled'], true), 422, 'Only failed or cancelled notifications can be retried.');

        $metadata = $notification->metadata ?: [];
        $retryCount = (int) ($metadata['retry_count'] ?? 0) + 1;
        $changedAt = now();
        $history = $metadata['status_history'] ?? [];
        $history[] = [
            'from' => $notification->status,
            'to' => 'queued',
            'note' => $note,
            'actor_user_id' => $actor->id,
            'changed_at' => $changedAt->toDateTimeString(),
            'retry_count' => $retryCount,
            'action' => 'retry',
        ];

        $notification->update([
            'status' => 'queued',
            'metadata' => array_merge($metadata, [
                'version' => 'PMC OS v0.075',
                'retry_count' => $retryCount,
                'last_retry_note' => $note,
                'last_retry_actor_id' => $actor->id,
                'last_retry_at' => $changedAt->toDateTimeString(),
                'next_retry_at' => $changedAt->copy()->addMinutes(min(60, 5 * $retryCount))->toDateTimeString(),
                'latest_status_note' => $note,
                'latest_status_actor_id' => $actor->id,
                'latest_status_changed_at' => $changedAt->toDateTimeString(),
                'status_history' => $history,
            ]),
        ]);

        $this->audit($actor, 'academic_pmc_v075_notification_retry_queued', 'PMC timetable notification retry queued', $notification, [
            'notification_id' => $notification->id,
            'retry_count' => $retryCount,
            'note' => $note,
        ]);

        return $notification->fresh();
    }

    private function createGeneralChangeImpactRecords(AcademicPmcTimetableChangeRequest $change): void
    {
        foreach (['faculty', 'students', 'rooms', 'groups', 'workload'] as $type) {
            AcademicPmcTimetableImpactRecord::create([
                'change_request_id' => $change->id,
                'impact_type' => $type,
                'title' => str($type)->headline() . ' affected by timetable change',
                'affected_count' => 0,
                'affected_records' => [],
                'metadata' => ['source' => 'general_change_request'],
            ]);
        }
    }

    private function createSessionChangeImpactRecords(AcademicPmcTimetableChangeRequest $change, AcademicPmcTimetableGenerationItem $item): void
    {
        $group = $item->courseGroup;
        $studentIds = $group
            ? $group->members->where('status', 'active')->pluck('student_id')->filter()->values()
            : collect();

        foreach ([
            [
                'type' => 'faculty',
                'title' => 'Faculty affected by session change',
                'count' => $item->teacher_id ? 1 : 0,
                'records' => array_filter([
                    'teacher_id' => $item->teacher_id,
                    'teacher_name' => $item->teacher?->user?->name,
                ]),
            ],
            [
                'type' => 'students',
                'title' => 'Students affected by session change',
                'count' => $studentIds->count(),
                'records' => ['student_ids' => $studentIds->all()],
            ],
            [
                'type' => 'rooms',
                'title' => 'Room affected by session change',
                'count' => $item->classroom_id ? 1 : 0,
                'records' => array_filter([
                    'classroom_id' => $item->classroom_id,
                    'room' => $item->classroom?->room_number ?? $item->classroom?->name,
                ]),
            ],
            [
                'type' => 'groups',
                'title' => 'Course group affected by session change',
                'count' => $item->course_group_id ? 1 : 0,
                'records' => array_filter([
                    'course_group_id' => $item->course_group_id,
                    'course_group' => $group?->name,
                    'subject_id' => $item->subject_id ?: $group?->subject_id,
                ]),
            ],
            [
                'type' => 'workload',
                'title' => 'Teaching workload affected by session change',
                'count' => max(1, (int) ($item->duration_slots ?? 1)),
                'records' => [
                    'duration_slots' => max(1, (int) ($item->duration_slots ?? 1)),
                    'day_of_week' => $item->day_of_week,
                    'timetable_slot_id' => $item->timetable_slot_id,
                    'slot' => $item->slot?->name,
                ],
            ],
        ] as $impact) {
            AcademicPmcTimetableImpactRecord::create([
                'change_request_id' => $change->id,
                'impact_type' => $impact['type'],
                'title' => $impact['title'],
                'affected_count' => $impact['count'],
                'affected_records' => $impact['records'],
                'metadata' => [
                    'source' => 'canonical_pmc_official_session',
                    'pmc_generation_item_id' => $item->id,
                    'timetable_version_id' => $item->timetable_version_id,
                ],
            ]);
        }
    }

    private function scoreSubstitutionCandidate(Teacher $candidate, ?AcademicPmcCourseGroup $courseGroup, int $dayOfWeek, ?int $slotId): array
    {
        $preference = AcademicPmcFacultyPreference::where('teacher_id', $candidate->id)
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $courseGroup?->term_id))
            ->first();
        $sameSlotConflict = $slotId
            ? $this->readModels->officialTimetableItemsQuery()
                ->where('teacher_id', $candidate->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $slotId)
                ->where('status', 'scheduled')
                ->exists()
            : false;
        $sameDayCount = $this->readModels->officialTimetableItemsQuery()
            ->where('teacher_id', $candidate->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'scheduled')
            ->count();
        $weeklyCount = $this->readModels->officialTimetableItemsQuery()
            ->where('teacher_id', $candidate->id)
            ->where('status', 'scheduled')
            ->count();
        $backupAssignment = $courseGroup
            ? AcademicPmcGroupFacultyAssignment::where('course_group_id', $courseGroup->id)
                ->where('teacher_id', $candidate->id)
                ->where(fn ($q) => $q->where('assignment_role', 'backup')->orWhere('is_backup', true))
                ->exists()
            : false;

        $score = 50;
        $reasons = ['active_faculty'];
        $checks = [
            'faculty' => 'clear',
            'availability' => 'not_declared',
            'workload' => 'clear',
        ];

        if ($sameSlotConflict) {
            $score -= 70;
            $reasons[] = 'same_slot_faculty_conflict';
            $checks['faculty'] = 'blocked_same_slot';
        } else {
            $score += 15;
            $reasons[] = 'no_same_slot_conflict';
        }

        if ($backupAssignment) {
            $score += 25;
            $reasons[] = 'assigned_backup_for_group';
        }

        $expertise = collect($preference?->subject_expertise ?? [])->map(fn ($value) => str($value)->lower()->toString());
        $subjectText = str(($courseGroup?->subject?->code ?? '') . ' ' . ($courseGroup?->subject?->name ?? ''))->lower()->toString();
        if ($expertise->isNotEmpty() && $expertise->contains(fn ($value) => $value !== '' && str_contains($subjectText, $value))) {
            $score += 20;
            $reasons[] = 'subject_expertise_match';
        } elseif ($courseGroup?->subject) {
            $score -= 5;
            $reasons[] = 'subject_expertise_not_confirmed';
        }

        $availableDays = collect($preference?->available_days ?? [])->map(fn ($value) => (int) $value)->filter()->values();
        if ($availableDays->isNotEmpty()) {
            if ($availableDays->contains($dayOfWeek)) {
                $score += 15;
                $reasons[] = 'available_on_substitution_day';
                $checks['availability'] = 'available_day';
            } else {
                $score -= ($preference?->faculty_type === 'adjunct') ? 60 : 35;
                $reasons[] = $preference?->faculty_type === 'adjunct' ? 'adjunct_not_available_that_day' : 'not_in_available_day_list';
                $checks['availability'] = 'blocked_day';
            }
        }

        $slotBlocked = $slotId && $this->isSlotUnavailable($preference?->unavailable_slots ?? [], $dayOfWeek, $slotId);
        if ($slotBlocked) {
            $score -= 60;
            $reasons[] = 'faculty_marked_slot_unavailable';
            $checks['availability'] = 'blocked_slot';
        }

        $maxDaily = (int) ($preference?->max_classes_per_day ?: 4);
        $maxWeekly = (int) ($preference?->max_weekly_load ?: 18);
        if ($sameDayCount + 1 > $maxDaily) {
            $score -= 20;
            $reasons[] = 'daily_load_limit_risk';
            $checks['workload'] = 'daily_limit_risk';
        } else {
            $score += 8;
            $reasons[] = 'daily_load_within_limit';
        }
        if ($weeklyCount + 1 > $maxWeekly) {
            $score -= 20;
            $reasons[] = 'weekly_load_limit_risk';
            $checks['workload'] = 'weekly_limit_risk';
        } else {
            $score += 8;
            $reasons[] = 'weekly_load_within_limit';
        }

        return [
            'teacher_id' => $candidate->id,
            'teacher_name' => $candidate->user?->name ?? $candidate->employee_id ?? 'Unassigned faculty',
            'score' => max(0, min(100, $score)),
            'reasons' => array_values(array_unique($reasons)),
            'conflict_checks' => $checks,
        ];
    }

    private function isSlotUnavailable(array $unavailableSlots, int $dayOfWeek, int $slotId): bool
    {
        foreach ($unavailableSlots as $key => $value) {
            if (is_array($value) && array_key_exists('day', $value) && array_key_exists('slot_id', $value)) {
                if ((int) $value['day'] === $dayOfWeek && (int) $value['slot_id'] === $slotId) {
                    return true;
                }
                continue;
            }

            if (is_numeric($key) && is_array($value)) {
                if ((int) $key === $dayOfWeek && in_array($slotId, array_map('intval', $value), true)) {
                    return true;
                }
                continue;
            }

            if (is_numeric($key) && is_numeric($value)) {
                if ((int) $key === $dayOfWeek && (int) $value === $slotId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function defaultResolutionActionType(AcademicPmcTimetableConstraint $constraint): string
    {
        return match ($constraint->constraint_type) {
            'faculty_clash', 'faculty_day_unavailable', 'faculty_slot_unavailable' => 'change_faculty_or_slot',
            'room_clash', 'room_capacity_mismatch', 'room_type_mismatch' => 'change_room',
            'student_clash' => 'move_group_slot',
            'unscheduled_class' => 'schedule_missing_class',
            default => 'manual_resolution',
        };
    }

    private function audit(User $actor, string $action, string $description, mixed $subject = null, array $metadata = []): void
    {
        DepartmentActivityLog::create([
            'department_id' => Department::where('code', 'ACAD')->value('id') ?: Department::query()->value('id'),
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'metadata' => $metadata + ['version' => 'PMC OS v0.041'],
        ]);
    }
}
