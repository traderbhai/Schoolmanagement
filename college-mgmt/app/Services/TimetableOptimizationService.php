<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicCalendar;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcFacultyTimetablePolicy;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomCapability;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\Classroom;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Support\Collection;

class TimetableOptimizationService
{
    public function __construct(private ConflictPreventionService $conflicts) {}

    public function buildDemand(array $scope): Collection
    {
        $groups = AcademicPmcCourseGroup::with(['subject', 'members', 'facultyAssignments.teacher'])
            ->when($scope['program_id'] ?? null, fn ($query, $id) => $query->where('program_id', $id))
            ->when($scope['batch_id'] ?? null, fn ($query, $id) => $query->where('batch_id', $id))
            ->when($scope['term_id'] ?? null, fn ($query, $id) => $query->where('term_id', $id))
            ->whereIn('status', ['active', 'locked', 'approved'])
            ->orderByDesc('is_locked')
            ->orderBy('id')
            ->get();

        return $groups->flatMap(function (AcademicPmcCourseGroup $group) {
            $assignment = $this->primaryAssignment($group);
            $constraints = $group->constraints ?: [];
            $mix = $constraints['session_mix'] ?? null;

            if (is_array($mix) && $mix !== []) {
                return collect($mix)->map(fn ($row, $type) => [
                    'group' => $group,
                    'assignment' => $assignment,
                    'session_type' => is_string($type) ? $type : ($row['type'] ?? 'lecture'),
                    'required_sessions_per_week' => max(1, (int) ($row['sessions'] ?? 1)),
                    'duration_slots' => max(1, (int) ($row['duration_slots'] ?? 1)),
                    'source' => 'group_session_mix',
                    'rules' => $row,
                ]);
            }

            $sessionType = str_contains((string) $group->group_type, 'lab') ? 'lab' : (str_contains((string) $group->group_type, 'tutorial') ? 'tutorial' : 'lecture');
            $duration = $sessionType === 'lab' ? 2 : 1;
            $weeklyHours = (int) ($assignment?->weekly_hours ?: ($constraints['weekly_hours'] ?? $group->subject?->credits ?? 3));

            return [[
                'group' => $group,
                'assignment' => $assignment,
                'session_type' => $sessionType,
                'required_sessions_per_week' => (int) ($constraints['weekly_sessions'] ?? max(1, (int) ceil($weeklyHours / $duration))),
                'duration_slots' => $duration,
                'source' => $assignment?->weekly_hours ? 'faculty_weekly_hours' : 'group_or_subject_defaults',
                'rules' => ['weekly_hours' => $weeklyHours, 'group_type' => $group->group_type, 'subject_credits' => $group->subject?->credits],
            ]];
        })->values();
    }

    public function solve(User $actor, array $scope, array $options = []): AcademicPmcTimetableGenerationRun
    {
        $demands = $this->buildDemand($scope);
        $slots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();
        $rooms = Classroom::where('is_active', true)->orderBy('capacity')->get();

        abort_if($demands->isEmpty(), 422, 'Timetable generation requires locked/active course groups with session demand.');
        abort_if($slots->isEmpty(), 422, 'Timetable generation requires active non-break teaching slots.');
        abort_if($rooms->isEmpty(), 422, 'Timetable generation requires active classrooms or labs.');

        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => $options['title'] ?? 'PMC Optimized Timetable',
            'strategy' => $options['strategy'] ?? 'balanced',
            'program_id' => $scope['program_id'] ?? null,
            'batch_id' => $scope['batch_id'] ?? null,
            'term_id' => $scope['term_id'] ?? null,
            'created_by' => $actor->id,
            'status' => 'generated',
            'input_summary' => [
                'groups' => $demands->pluck('group.id')->unique()->count(),
                'session_demands' => $demands->count(),
                'teaching_slots' => $slots->count(),
                'rooms' => $rooms->count(),
                'optimizer' => 'laravel_canonical_optimizer',
                'calendar_exceptions' => $this->calendarExceptionSummary($scope),
                'version' => 'PMC OS canonical hardening',
            ],
        ]);

        $occupied = ['teacher' => [], 'room' => [], 'group' => [], 'student' => []];
        $scheduled = 0;
        $unscheduled = 0;

        foreach ($this->orderedDemands($demands) as $demandSpec) {
            /** @var AcademicPmcCourseGroup $group */
            $group = $demandSpec['group'];
            $assignment = $demandSpec['assignment'];
            $teacherId = $assignment?->teacher_id;
            $demand = AcademicPmcTimetableSessionDemand::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $group->id,
                'session_type' => $demandSpec['session_type'],
                'required_sessions_per_week' => $demandSpec['required_sessions_per_week'],
                'duration_slots' => $demandSpec['duration_slots'],
                'source' => $demandSpec['source'],
                'rules' => $demandSpec['rules'],
                'metadata' => ['optimizer' => 'laravel_canonical_optimizer'],
            ]);

            $demandScheduled = 0;
            $demandUnscheduled = 0;

            for ($index = 1; $index <= $demand->required_sessions_per_week; $index++) {
                $result = $teacherId
                    ? $this->placeSession($group, (int) $teacherId, $demand, $index, $rooms, $slots, $occupied, (string) $run->strategy)
                    : null;

                if (! $result) {
                    $diagnostics = $teacherId
                        ? $this->unscheduledDiagnostics($group, (int) $teacherId, $demand, $rooms)
                        : [
                            'primary_blocker' => 'missing_primary_faculty',
                            'blockers' => ['missing_primary_faculty'],
                            'recommended_actions' => ['Assign a primary faculty member.'],
                        ];

                    AcademicPmcTimetableGenerationItem::create([
                        'generation_run_id' => $run->id,
                        'course_group_id' => $group->id,
                        'session_demand_id' => $demand->id,
                        'program_id' => $group->program_id,
                        'batch_id' => $group->batch_id,
                        'term_id' => $group->term_id,
                        'subject_id' => $group->subject_id,
                        'session_index' => $index,
                        'session_type' => $demand->session_type,
                        'duration_slots' => $demand->duration_slots,
                        'status' => 'unscheduled',
                        'official_status' => 'draft',
                        'source_type' => 'optimizer',
                        'explanation' => $teacherId ? $diagnostics['summary'] : 'Missing primary faculty for required weekly session.',
                        'metadata' => [
                            'solver_pass' => 'unscheduled',
                            'unscheduled_diagnostics' => $diagnostics,
                            'hard_constraint_explanations' => $diagnostics['blockers'],
                            'recommended_actions' => $diagnostics['recommended_actions'],
                        ],
                    ]);
                    $unscheduled++;
                    $demandUnscheduled++;
                    continue;
                }

                $item = AcademicPmcTimetableGenerationItem::create([
                    'generation_run_id' => $run->id,
                    'course_group_id' => $group->id,
                    'session_demand_id' => $demand->id,
                    'program_id' => $group->program_id,
                    'batch_id' => $group->batch_id,
                    'term_id' => $group->term_id,
                    'subject_id' => $group->subject_id,
                    'session_index' => $index,
                    'session_type' => $demand->session_type,
                    'duration_slots' => $demand->duration_slots,
                    'teacher_id' => $teacherId,
                    'classroom_id' => $result['room']->id,
                    'day_of_week' => $result['day'],
                    'timetable_slot_id' => $result['slot']->id,
                    'status' => 'scheduled',
                    'official_status' => 'draft',
                    'source_type' => 'optimizer',
                    'is_locked' => $result['solver_pass'] === 'hard_lock',
                    'confidence' => $result['score'],
                    'explanation' => 'Placed by canonical Laravel optimizer using hard constraints and soft objective scoring.',
                    'metadata' => [
                        'solver_pass' => $result['solver_pass'],
                        'candidate_score' => $result['score'],
                        'soft_constraint_explanations' => $result['reasons'],
                        'rejected_alternatives' => $result['rejected'],
                        'placement_alternatives' => $result['alternatives'],
                    ],
                ]);
                $this->markOccupied($occupied, $item, $group, $slots);
                $scheduled++;
                $demandScheduled++;
            }

            $demand->update([
                'scheduled_sessions' => $demandScheduled,
                'unscheduled_sessions' => $demandUnscheduled,
                'status' => $demandUnscheduled > 0 ? 'partially_scheduled' : 'scheduled',
            ]);
        }

        $run->update(['scheduled_count' => $scheduled, 'unscheduled_count' => $unscheduled]);
        AcademicPmcTimetableSolverAttempt::create([
            'generation_run_id' => $run->id,
            'strategy' => $run->strategy,
            'status' => $unscheduled > 0 ? 'completed_with_unscheduled' : 'completed',
            'placements_attempted' => $scheduled + $unscheduled,
            'placements_scheduled' => $scheduled,
            'placements_unscheduled' => $unscheduled,
            'diagnostics' => [
                'optimizer' => 'laravel_canonical_optimizer',
                'passes' => ['hard_lock', 'lab_long_block', 'scarce_room', 'high_overlap', 'regular', 'repair_retry'],
            ],
        ]);

        return $run->fresh();
    }

    public function scoreCandidate(array $candidate): array
    {
        /** @var AcademicPmcCourseGroup $group */
        $group = $candidate['group'];
        /** @var Classroom $room */
        $room = $candidate['room'];
        $teacherDayLoad = (int) ($candidate['teacher_day_load'] ?? 0);
        $groupDayLoad = (int) ($candidate['group_day_load'] ?? 0);
        $preferred = (bool) ($candidate['preferred_slot'] ?? false);
        $roomWaste = max(0, (int) ($room->capacity ?? 0) - (int) $group->current_strength);
        $strategy = (string) ($candidate['strategy'] ?? 'balanced');
        $maxDaily = max(1, (int) ($candidate['max_daily_classes'] ?? 4));

        $score = 50 + max(0, 20 - min(20, (int) floor($roomWaste / 5))) + max(0, 20 - ($teacherDayLoad * 5)) + max(0, 15 - ($groupDayLoad * 4)) + ($preferred ? 10 : 0);
        $reasons = ['strategy_' . $strategy, 'teacher_day_load_' . $teacherDayLoad, 'group_day_load_' . $groupDayLoad];
        if ($teacherDayLoad + 1 > $maxDaily) {
            $score -= 15;
            $reasons[] = 'faculty_daily_policy_pressure';
        }
        if ($roomWaste <= 10) {
            $reasons[] = 'room_capacity_close_fit';
        }
        if ($preferred) {
            $reasons[] = 'faculty_preferred_slot';
        }

        return ['score' => max(1, min(100, $score)), 'reasons' => array_values(array_unique($reasons))];
    }

    public function explainRejectedCandidate(array $candidate): array
    {
        $group = $candidate['group'] ?? null;
        if (! $group instanceof AcademicPmcCourseGroup) {
            return ['invalid_course_group'];
        }

        $messages = [];
        if (empty($candidate['teacher_id'])) {
            $messages[] = 'missing_primary_faculty';
        }
        if ((int) $group->current_strength <= 0) {
            $messages[] = 'empty_or_unconfirmed_group_strength';
        }

        return $messages ?: ['all_candidate_slots_failed_hard_constraints'];
    }

    private function unscheduledDiagnostics(AcademicPmcCourseGroup $group, int $teacherId, AcademicPmcTimetableSessionDemand $demand, Collection $rooms): array
    {
        $candidateRooms = $this->candidateRooms($rooms, $group, null, 'regular');
        $blockers = $this->explainRejectedCandidate(['group' => $group, 'teacher_id' => $teacherId, 'demand' => $demand]);

        if ($candidateRooms->isEmpty()) {
            array_unshift($blockers, 'no_candidate_rooms');
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'primary_blocker' => $blockers[0] ?? 'no_feasible_candidate',
            'blockers' => $blockers,
            'blocker_counts' => array_count_values($blockers),
            'candidate_rooms' => $candidateRooms->map(fn ($room) => ['id' => $room->id, 'name' => $room->name ?? $room->room_number])->values()->all(),
            'recommended_actions' => in_array('no_candidate_rooms', $blockers, true)
                ? ['Add or activate a suitable room with sufficient capacity and required lab capability.']
                : ['Relax constraints, add room capacity, or approve a faculty/room alternative.'],
            'summary' => 'Primary blocker: ' . str_replace('_', ' ', $blockers[0] ?? 'no feasible candidate') . '. No feasible placement was found by the canonical optimizer.',
        ];
    }

    private function orderedDemands(Collection $demands): Collection
    {
        return $demands->sortByDesc(function (array $demand) {
            $group = $demand['group'];
            $type = (string) $demand['session_type'];
            return ((int) $group->is_locked * 1000)
                + (str_contains($type, 'lab') || (int) $demand['duration_slots'] > 1 ? 500 : 0)
                + ($group->members->count() * 2)
                + ((int) $demand['duration_slots'] * 10);
        })->values();
    }

    private function placeSession(AcademicPmcCourseGroup $group, int $teacherId, AcademicPmcTimetableSessionDemand $demand, int $sessionIndex, Collection $rooms, Collection $slots, array $occupied, string $strategy): ?array
    {
        $preference = AcademicPmcFacultyPreference::where('teacher_id', $teacherId)
            ->where(fn ($query) => $query->where('term_id', $group->term_id)->orWhereNull('term_id'))
            ->first();
        $policy = AcademicPmcFacultyTimetablePolicy::where('status', 'active')
            ->where(fn ($query) => $query->where('teacher_id', $teacherId)->orWhereNull('teacher_id'))
            ->where(fn ($query) => $query->where('term_id', $group->term_id)->orWhereNull('term_id'))
            ->where(fn ($query) => $query->where('program_id', $group->program_id)->orWhereNull('program_id'))
            ->orderByRaw('teacher_id is null')
            ->orderByRaw('term_id is null')
            ->first();
        $locked = AcademicPmcLockedSlot::where('status', 'active')->where('is_hard_lock', true)->where('course_group_id', $group->id)->first();
        $passes = $this->passesFor($group, $demand, $locked);
        $rejected = [];

        foreach ($passes as $pass) {
            $candidates = collect();
            foreach ($this->candidateDays($preference, $sessionIndex, $strategy, $policy) as $day) {
                foreach ($this->candidateSlots($slots, $locked, $pass) as $slot) {
                    foreach ($this->candidateRooms($rooms, $group, $locked, $pass) as $room) {
                        if (! $this->freeInCurrentRun($group, $teacherId, (int) $room->id, (int) $day, (int) $slot->id, (int) $demand->duration_slots, $slots, $occupied)) {
                            $rejected[] = ['day' => $day, 'slot_id' => $slot->id, 'room_id' => $room->id, 'reason' => 'occupied_in_current_run'];
                            continue;
                        }

                        $available = $this->conflicts->isSlotAvailable((int) $day, (int) $slot->id, $teacherId, (int) $room->id, (int) $group->batch_id, (int) $group->term_id, $group->id, (int) $demand->duration_slots);
                        if (! $available['available']) {
                            $rejected[] = ['day' => $day, 'slot_id' => $slot->id, 'room_id' => $room->id, 'reason' => implode('; ', $available['conflicts'])];
                            continue;
                        }

                        $score = $this->scoreCandidate([
                            'group' => $group,
                            'room' => $room,
                            'teacher_day_load' => $this->occupiedCount($occupied, 'teacher', $teacherId, (int) $day),
                            'group_day_load' => $this->occupiedCount($occupied, 'group', $group->id, (int) $day),
                            'preferred_slot' => in_array((int) $slot->id, array_map('intval', $preference?->preferred_slots ?: []), true),
                            'max_daily_classes' => $policy?->max_daily_classes,
                            'strategy' => $strategy,
                        ]);
                        $candidates->push(['day' => (int) $day, 'slot' => $slot, 'room' => $room, 'score' => $score['score'], 'reasons' => array_merge([$pass], $score['reasons']), 'solver_pass' => $pass]);
                    }
                }
            }

            $ranked = $candidates->sortByDesc('score')->values();
            if ($ranked->isNotEmpty()) {
                $best = $ranked->first();
                $best['alternatives'] = $ranked->skip(1)->take(3)->map(fn ($candidate) => [
                    'day' => $candidate['day'],
                    'slot_id' => $candidate['slot']->id,
                    'slot_name' => $candidate['slot']->name,
                    'room_id' => $candidate['room']->id,
                    'room_name' => $candidate['room']->name,
                    'score' => $candidate['score'],
                    'reasons' => $candidate['reasons'],
                ])->values()->all();
                $best['rejected'] = array_slice($rejected, 0, 8);
                return $best;
            }
        }

        return null;
    }

    private function passesFor(AcademicPmcCourseGroup $group, AcademicPmcTimetableSessionDemand $demand, ?AcademicPmcLockedSlot $locked): array
    {
        if ($locked) {
            return ['hard_lock'];
        }
        if (str_contains((string) $demand->session_type, 'lab') || (int) $demand->duration_slots > 1) {
            return ['lab_long_block', 'repair_retry'];
        }
        if ($group->members->count() >= 60) {
            return ['scarce_room', 'repair_retry'];
        }
        if (in_array($group->group_type, ['elective', 'open_elective', 'shared_elective'], true)) {
            return ['high_overlap', 'repair_retry'];
        }
        return ['regular', 'repair_retry'];
    }

    private function candidateDays(?AcademicPmcFacultyPreference $preference, int $sessionIndex, string $strategy, ?AcademicPmcFacultyTimetablePolicy $policy = null): array
    {
        $policyDays = $policy?->allowed_days ? array_map('intval', $policy->allowed_days) : null;
        $days = array_values(array_map('intval', $preference?->available_days ?: ($policyDays ?: range(1, 6))));
        if ($policyDays) {
            $days = array_values(array_intersect($days, $policyDays));
        }
        if ($strategy === 'adjunct_priority') {
            return $days;
        }
        $offset = ($sessionIndex - 1) % max(count($days), 1);
        return array_values(array_unique(array_merge(array_slice($days, $offset), array_slice($days, 0, $offset))));
    }

    private function candidateSlots(Collection $slots, ?AcademicPmcLockedSlot $locked, string $pass): Collection
    {
        if ($locked && $locked->timetable_slot_id) {
            return $slots->where('id', $locked->timetable_slot_id)->values();
        }
        return $pass === 'repair_retry' ? $slots->sortByDesc('sort_order')->values() : $slots;
    }

    private function candidateRooms(Collection $rooms, AcademicPmcCourseGroup $group, ?AcademicPmcLockedSlot $locked, string $pass): Collection
    {
        if ($locked && $locked->classroom_id) {
            return $rooms->where('id', $locked->classroom_id)->values();
        }
        $requiresLab = str_contains((string) $group->group_type, 'lab');
        return $rooms
            ->filter(fn ($room) => ($room->capacity ?? 0) >= (int) $group->current_strength)
            ->when($requiresLab, fn ($collection) => $collection->filter(fn ($room) => $this->roomSupportsLab($room)))
            ->sortBy(fn ($room) => $pass === 'scarce_room' ? ($room->capacity ?? 0) : abs(($room->capacity ?? 0) - (int) $group->current_strength))
            ->values();
    }

    private function roomSupportsLab(Classroom $room): bool
    {
        return (bool) $room->has_lab
            || $room->type === 'lab'
            || AcademicPmcRoomCapability::where('classroom_id', $room->id)
                ->where('is_active', true)
                ->where('capability_type', 'lab')
                ->exists();
    }

    private function calendarExceptionSummary(array $scope): array
    {
        $events = AcademicCalendar::query()
            ->when($scope['term_id'] ?? null, fn ($query, $termId) => $query->where('term_id', $termId))
            ->where(fn ($query) => $query->where('is_holiday', true)->orWhereIn('event_type', ['exam_week', 'event']))
            ->orderBy('event_date')
            ->limit(20)
            ->get();

        return [
            'count' => $events->count(),
            'events' => $events->map(fn (AcademicCalendar $event) => [
                'date' => optional($event->event_date)->toDateString(),
                'day_of_week' => optional($event->event_date)->dayOfWeekIso,
                'type' => $event->event_type,
                'name' => $event->event_name,
                'is_holiday' => (bool) $event->is_holiday,
            ])->values()->all(),
            'weekly_pattern_note' => 'Date-specific calendar events are retained as publish/operations exceptions; weekly generation is not globally blocked by a single dated holiday.',
        ];
    }

    private function freeInCurrentRun(AcademicPmcCourseGroup $group, int $teacherId, int $roomId, int $day, int $slotId, int $durationSlots, Collection $slots, array $occupied): bool
    {
        $slotIds = $this->coveredSlotIds($slots, $slotId, $durationSlots);
        if (count($slotIds) < $durationSlots) {
            return false;
        }

        foreach ($slotIds as $coveredSlotId) {
            $key = $day . '-' . $coveredSlotId;
            if (isset($occupied['teacher'][$teacherId][$key]) || isset($occupied['room'][$roomId][$key]) || isset($occupied['group'][$group->id][$key])) {
                return false;
            }
            foreach ($group->members as $member) {
                if (isset($occupied['student'][$member->student_id][$key])) {
                    return false;
                }
            }
        }
        return true;
    }

    private function markOccupied(array &$occupied, AcademicPmcTimetableGenerationItem $item, AcademicPmcCourseGroup $group, Collection $slots): void
    {
        foreach ($this->coveredSlotIds($slots, (int) $item->timetable_slot_id, (int) $item->duration_slots) as $slotId) {
            $key = $item->day_of_week . '-' . $slotId;
            $occupied['teacher'][$item->teacher_id][$key] = true;
            $occupied['room'][$item->classroom_id][$key] = true;
            $occupied['group'][$group->id][$key] = true;
            foreach ($group->members as $member) {
                $occupied['student'][$member->student_id][$key] = true;
            }
        }
    }

    private function coveredSlotIds(Collection $slots, int $slotId, int $durationSlots): array
    {
        $start = $slots->firstWhere('id', $slotId)?->sort_order;
        if ($start === null) {
            return [$slotId];
        }
        return $slots
            ->filter(fn ($slot) => (int) $slot->sort_order >= (int) $start && (int) $slot->sort_order < ((int) $start + $durationSlots))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function occupiedCount(array $occupied, string $type, int $id, int $day): int
    {
        return collect($occupied[$type][$id] ?? [])->keys()->filter(fn ($key) => str_starts_with((string) $key, $day . '-'))->count();
    }

    private function primaryAssignment(AcademicPmcCourseGroup $group): ?AcademicPmcGroupFacultyAssignment
    {
        return $group->facultyAssignments->firstWhere('assignment_role', 'primary') ?: $group->facultyAssignments->first();
    }
}
