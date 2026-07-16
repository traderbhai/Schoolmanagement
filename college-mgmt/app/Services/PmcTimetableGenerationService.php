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
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\Classroom;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Support\Collection;

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

    public function createSessionDemands(AcademicPmcTimetableGenerationRun $run, AcademicPmcCourseGroup $group, ?AcademicPmcGroupFacultyAssignment $assignment): Collection
    {
        $constraints = $group->constraints ?: [];
        if (! empty($constraints['session_mix']) && is_array($constraints['session_mix'])) {
            return collect($constraints['session_mix'])->map(function ($mix, $type) use ($run, $group) {
                return AcademicPmcTimetableSessionDemand::create([
                    'generation_run_id' => $run->id,
                    'course_group_id' => $group->id,
                    'session_type' => is_string($type) ? $type : ($mix['type'] ?? 'lecture'),
                    'required_sessions_per_week' => max(1, (int) ($mix['sessions'] ?? 1)),
                    'duration_slots' => max(1, (int) ($mix['duration_slots'] ?? 1)),
                    'source' => 'group_session_mix',
                    'rules' => $mix,
                    'metadata' => ['version' => 'PMC OS v0.062'],
                ]);
            })->values();
        }

        $sessionType = str_contains($group->group_type, 'lab') ? 'lab' : (str_contains($group->group_type, 'tutorial') ? 'tutorial' : 'lecture');
        $duration = $sessionType === 'lab' ? 2 : 1;
        $weeklyHours = (int) ($assignment?->weekly_hours ?: ($constraints['weekly_hours'] ?? $group->subject?->credits ?? 3));
        $sessions = (int) ($constraints['weekly_sessions'] ?? max(1, (int) ceil($weeklyHours / $duration)));

        return collect([AcademicPmcTimetableSessionDemand::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'session_type' => $sessionType,
            'required_sessions_per_week' => $sessions,
            'duration_slots' => $duration,
            'source' => $assignment?->weekly_hours ? 'faculty_weekly_hours' : 'group_or_subject_defaults',
            'rules' => ['weekly_hours' => $weeklyHours, 'group_type' => $group->group_type, 'subject_credits' => $group->subject?->credits],
            'metadata' => ['version' => 'PMC OS v0.062'],
        ])]);
    }

    public function findFeasiblePlacement(AcademicPmcCourseGroup $group, int $teacherId, Collection $rooms, Collection $slots, ?AcademicPmcFacultyPreference $preference, ?AcademicPmcLockedSlot $locked, AcademicPmcTimetableSessionDemand $demand, int $sessionIndex, array $occupied, string $strategy): ?array
    {
        if ($locked && $sessionIndex === 1) {
            $slot = $slots->firstWhere('id', $locked->timetable_slot_id);
            $room = $locked->classroom_id ? $rooms->firstWhere('id', $locked->classroom_id) : $this->bestRoomForGroup($rooms, $group);
            if ($slot && $room && $this->isPlacementFree($group, $teacherId, $room->id, (int) $locked->day_of_week, $slot->id, $preference, $occupied, $slots, $demand->duration_slots)) {
                return [(int) $locked->day_of_week, $slot, $room, true, 100, ['hard_locked_slot']];
            }
        }

        $days = $this->candidateDays($preference, $sessionIndex, $strategy);
        $candidates = collect();
        foreach ($days as $day) {
            foreach ($slots as $slot) {
                if ($this->isSlotUnavailable($preference?->unavailable_slots ?? [], (int) $day, (int) $slot->id)) {
                    continue;
                }

                foreach ($this->candidateRooms($rooms, $group) as $room) {
                    if ($this->isPlacementFree($group, $teacherId, $room->id, (int) $day, (int) $slot->id, $preference, $occupied, $slots, $demand->duration_slots)) {
                        [$score, $reasons] = $this->scorePlacementCandidate($group, $teacherId, $room, (int) $day, $slot, $preference, $occupied, $slots, $demand, $strategy);
                        $candidates->push([
                            'day' => (int) $day,
                            'slot' => $slot,
                            'room' => $room,
                            'score' => $score,
                            'reasons' => $reasons,
                        ]);
                    }
                }
            }
        }

        $rankedCandidates = $candidates
            ->sort(function ($a, $b) {
                return ($b['score'] <=> $a['score'])
                    ?: ($a['day'] <=> $b['day'])
                    ?: (($a['slot']->sort_order ?? 0) <=> ($b['slot']->sort_order ?? 0))
                    ?: (($a['room']->capacity ?? 0) <=> ($b['room']->capacity ?? 0));
            })
            ->values();

        $best = $rankedCandidates->first();
        $alternatives = $rankedCandidates
            ->skip(1)
            ->take(3)
            ->map(fn ($candidate) => [
                'day' => $candidate['day'],
                'slot_id' => $candidate['slot']->id,
                'slot_name' => $candidate['slot']->name,
                'slot_order' => $candidate['slot']->sort_order,
                'room_id' => $candidate['room']->id,
                'room_name' => $candidate['room']->name,
                'score' => $candidate['score'],
                'reasons' => $candidate['reasons'],
            ])
            ->values()
            ->all();

        return $best ? [$best['day'], $best['slot'], $best['room'], false, $best['score'], $best['reasons'], $alternatives] : null;
    }

    public function placementFailureDiagnostics(AcademicPmcCourseGroup $group, int $teacherId, Collection $rooms, Collection $slots, ?AcademicPmcFacultyPreference $preference, array $occupied, int $durationSlots, string $strategy): array
    {
        $candidateDays = $this->candidateDays($preference, 1, $strategy);
        $candidateRooms = $this->candidateRooms($rooms, $group);
        $blockers = [
            'no_candidate_days' => count($candidateDays) === 0 ? 1 : 0,
            'no_candidate_rooms' => $candidateRooms->isEmpty() ? 1 : 0,
            'incomplete_multi_slot_block' => 0,
            'faculty_unavailable' => 0,
            'hard_lock_blocked' => 0,
            'occupied_resource_or_student' => 0,
        ];
        $sampledCandidates = [];

        foreach ($candidateDays as $day) {
            foreach ($slots as $slot) {
                $slotId = (int) $slot->id;
                $blockSlotIds = $this->blockSlotIds($slotId, $durationSlots);

                if (count($blockSlotIds) < $durationSlots) {
                    $blockers['incomplete_multi_slot_block']++;
                    $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'reason' => 'incomplete_multi_slot_block'];
                    continue;
                }

                $facultyUnavailable = collect($blockSlotIds)->contains(fn (int $blockSlotId): bool =>
                    $this->isSlotUnavailable($preference?->unavailable_slots ?? [], (int) $day, $blockSlotId)
                );
                if ($facultyUnavailable) {
                    $blockers['faculty_unavailable']++;
                    $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'reason' => 'faculty_unavailable'];
                    continue;
                }

                foreach ($candidateRooms as $room) {
                    $hardLocked = false;
                    $occupiedConflict = false;

                    foreach ($blockSlotIds as $blockSlotId) {
                        if ($this->placementBlockedByHardLock($group, $teacherId, (int) $room->id, (int) $day, $blockSlotId)) {
                            $hardLocked = true;
                            break;
                        }

                        $key = $day . '-' . $blockSlotId;
                        if (isset($occupied['teacher'][$teacherId][$key]) || isset($occupied['room'][$room->id][$key]) || isset($occupied['group'][$group->id][$key])) {
                            $occupiedConflict = true;
                            break;
                        }

                        foreach ($group->members as $member) {
                            if (isset($occupied['student'][$member->student_id][$key])) {
                                $occupiedConflict = true;
                                break 2;
                            }
                        }
                    }

                    if ($hardLocked) {
                        $blockers['hard_lock_blocked']++;
                        $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'room_id' => (int) $room->id, 'reason' => 'hard_lock_blocked'];
                        continue;
                    }

                    if ($occupiedConflict) {
                        $blockers['occupied_resource_or_student']++;
                        $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'room_id' => (int) $room->id, 'reason' => 'occupied_resource_or_student'];
                    }
                }
            }
        }

        $activeBlockers = collect($blockers)->filter(fn (int $count): bool => $count > 0)->sortDesc();
        $primary = (string) ($activeBlockers->keys()->first() ?: 'no_feasible_candidate');

        return [
            'summary' => 'No feasible slot found. Primary blocker: ' . str_replace('_', ' ', $primary) . '.',
            'primary_blocker' => $primary,
            'blockers' => $activeBlockers->keys()->values()->all(),
            'blocker_counts' => $blockers,
            'candidate_days' => array_values($candidateDays),
            'candidate_rooms' => $candidateRooms->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'sampled_blocked_candidates' => collect($sampledCandidates)->take(8)->values()->all(),
            'recommended_actions' => $this->recommendedActionsForPlacementBlockers($primary),
        ];
    }

    private function scorePlacementCandidate(AcademicPmcCourseGroup $group, int $teacherId, Classroom $room, int $day, TimetableSlot $slot, ?AcademicPmcFacultyPreference $preference, array $occupied, Collection $slots, AcademicPmcTimetableSessionDemand $demand, string $strategy): array
    {
        $teacherDayLoad = $this->occupiedCountOnDay($occupied, 'teacher', $teacherId, $day);
        $groupDayLoad = $this->occupiedCountOnDay($occupied, 'group', $group->id, $day);
        $adjacentGroup = $this->hasAdjacentOccupiedSlot($occupied, 'group', $group->id, $day, (int) $slot->id);
        $preferredSlot = in_array((int) $slot->id, array_map('intval', $preference?->preferred_slots ?: []), true);
        $roomWaste = max(0, (int) ($room->capacity ?? 0) - (int) $group->current_strength);
        $maxDaily = (int) ($preference?->max_classes_per_day ?: 4);
        $maxConsecutive = (int) ($preference?->max_consecutive_classes ?: 3);

        $score = 80;
        $reasons = [];

        $roomFitScore = max(0, 24 - min(24, (int) floor($roomWaste / 5)));
        $facultyBalanceScore = max(0, 24 - ($teacherDayLoad * 8));
        $studentCompactScore = $adjacentGroup ? 24 : max(8, 18 - ($groupDayLoad * 3));
        $preferenceScore = $preferredSlot ? 12 : 0;

        if ($teacherDayLoad + (int) $demand->duration_slots > $maxDaily) {
            $facultyBalanceScore -= 10;
            $reasons[] = 'near_faculty_daily_limit';
        }

        if ($this->wouldCreateConsecutivePressure($occupied, 'teacher', $teacherId, $day, (int) $slot->id, (int) $demand->duration_slots, $maxConsecutive, $slots)) {
            $facultyBalanceScore -= 8;
            $reasons[] = 'consecutive_teaching_pressure';
        }

        if ($adjacentGroup) {
            $reasons[] = 'keeps_student_day_compact';
        }
        if ($preferredSlot) {
            $reasons[] = 'faculty_preferred_slot';
        }
        if ($roomWaste <= 10) {
            $reasons[] = 'room_capacity_close_fit';
        }

        $score += match ($strategy) {
            'student_compact' => ($studentCompactScore * 2) + (int) round($facultyBalanceScore / 2) + (int) round($roomFitScore / 3) + $preferenceScore,
            'faculty_balanced' => ($facultyBalanceScore * 2) + (int) round($studentCompactScore / 2) + (int) round($roomFitScore / 3) + $preferenceScore,
            'adjunct_priority' => $preferenceScore + ($facultyBalanceScore * 2) + (int) round($studentCompactScore / 2) + (int) round($roomFitScore / 3),
            'room_optimized' => ($roomFitScore * 2) + $facultyBalanceScore + (int) round($studentCompactScore / 2) + $preferenceScore,
            default => $studentCompactScore + $facultyBalanceScore + $roomFitScore + $preferenceScore,
        };

        $reasons[] = 'strategy_' . ($strategy ?: 'balanced');
        $reasons[] = 'teacher_day_load_' . $teacherDayLoad;
        $reasons[] = 'group_day_load_' . $groupDayLoad;

        return [max(1, min(100, (int) round($score / 2))), array_values(array_unique($reasons))];
    }

    private function candidateDays(?AcademicPmcFacultyPreference $preference, int $sessionIndex, string $strategy): array
    {
        $days = array_values(array_map('intval', $preference?->available_days ?: range(1, 6)));
        if ($strategy === 'adjunct_priority') {
            return $days;
        }

        $offset = ($sessionIndex - 1) % max(count($days), 1);
        return array_values(array_unique(array_merge(array_slice($days, $offset), array_slice($days, 0, $offset))));
    }

    private function candidateRooms(Collection $rooms, AcademicPmcCourseGroup $group): Collection
    {
        $requiresLab = str_contains($group->group_type, 'lab');
        return $rooms
            ->filter(fn ($room) => ($room->capacity ?? 0) >= $group->current_strength)
            ->when($requiresLab, fn ($collection) => $collection->filter(fn ($room) => $room->has_lab || $room->type === 'lab'))
            ->sortBy('capacity')
            ->values();
    }

    private function isPlacementFree(AcademicPmcCourseGroup $group, int $teacherId, int $roomId, int $day, int $slotId, ?AcademicPmcFacultyPreference $preference, array $occupied, Collection $slots, int $durationSlots = 1): bool
    {
        if (! empty($preference?->available_days) && ! in_array($day, array_map('intval', $preference->available_days), true)) {
            return false;
        }

        $blockSlotIds = $this->blockSlotIds($slotId, $durationSlots);
        if (count($blockSlotIds) < $durationSlots) {
            return false;
        }

        foreach ($blockSlotIds as $blockSlotId) {
            if ($this->isSlotUnavailable($preference?->unavailable_slots ?? [], $day, $blockSlotId)) {
                return false;
            }

            if ($this->placementBlockedByHardLock($group, $teacherId, $roomId, $day, $blockSlotId)) {
                return false;
            }

            $key = $day . '-' . $blockSlotId;
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

    private function placementBlockedByHardLock(AcademicPmcCourseGroup $group, int $teacherId, int $roomId, int $day, int $slotId): bool
    {
        $members = $group->relationLoaded('members')
            ? $group->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : $group->members()->where('status', 'active')->pluck('student_id')->filter()->unique();

        $locks = AcademicPmcLockedSlot::with('courseGroup.members')
            ->where('status', 'active')
            ->where('is_hard_lock', true)
            ->where('day_of_week', $day)
            ->where('timetable_slot_id', $slotId)
            ->where(fn ($query) => $query->where('term_id', $group->term_id)->orWhereNull('term_id'))
            ->get();

        foreach ($locks as $lock) {
            if ((int) $lock->course_group_id === (int) $group->id) {
                if ($lock->teacher_id && (int) $lock->teacher_id !== $teacherId) {
                    return true;
                }

                if ($lock->classroom_id && (int) $lock->classroom_id !== $roomId) {
                    return true;
                }

                continue;
            }

            if ($lock->teacher_id && (int) $lock->teacher_id === $teacherId) {
                return true;
            }

            if ($lock->classroom_id && (int) $lock->classroom_id === $roomId) {
                return true;
            }

            if ($lock->course_group_id && $this->hardLockGroupMembersOverlap($lock, $members)) {
                return true;
            }

            if (! $lock->course_group_id && $this->hardLockScopeBlocksGroup($lock, $group)) {
                return true;
            }
        }

        return false;
    }

    private function hardLockGroupMembersOverlap(AcademicPmcLockedSlot $lock, Collection $candidateMembers): bool
    {
        if ($candidateMembers->isEmpty()) {
            return false;
        }

        $lockedMembers = $lock->courseGroup?->members
            ? $lock->courseGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : collect();

        return $lockedMembers->isNotEmpty() && $candidateMembers->intersect($lockedMembers)->isNotEmpty();
    }

    private function hardLockScopeBlocksGroup(AcademicPmcLockedSlot $lock, AcademicPmcCourseGroup $group): bool
    {
        if ($lock->batch_id && (int) $lock->batch_id === (int) $group->batch_id) {
            return true;
        }

        if (! $lock->batch_id && $lock->program_id && (int) $lock->program_id === (int) $group->program_id) {
            return true;
        }

        return ! $lock->program_id
            && ! $lock->batch_id
            && ! $lock->teacher_id
            && ! $lock->classroom_id;
    }

    private function occupiedCountOnDay(array $occupied, string $type, int $id, int $day): int
    {
        return collect($occupied[$type][$id] ?? [])
            ->keys()
            ->filter(fn ($key) => str_starts_with((string) $key, $day . '-'))
            ->count();
    }

    private function hasAdjacentOccupiedSlot(array $occupied, string $type, int $id, int $day, int $slotId): bool
    {
        $slotOrders = TimetableSlot::where('is_active', true)->pluck('sort_order', 'id');
        $targetOrder = $slotOrders[$slotId] ?? null;
        if ($targetOrder === null) {
            return false;
        }

        foreach (array_keys($occupied[$type][$id] ?? []) as $key) {
            [$occupiedDay, $occupiedSlotId] = array_map('intval', explode('-', (string) $key) + [0, 0]);
            if ($occupiedDay !== $day) {
                continue;
            }

            $occupiedOrder = $slotOrders[$occupiedSlotId] ?? null;
            if ($occupiedOrder !== null && abs((int) $occupiedOrder - (int) $targetOrder) === 1) {
                return true;
            }
        }

        return false;
    }

    private function bestRoomForGroup(Collection $rooms, AcademicPmcCourseGroup $group): ?Classroom
    {
        $requiresLab = str_contains($group->group_type, 'lab');
        return $rooms
            ->filter(fn ($room) => ($room->capacity ?? 0) >= $group->current_strength)
            ->when($requiresLab, fn ($collection) => $collection->filter(fn ($room) => $room->has_lab || $room->type === 'lab'))
            ->sortBy('capacity')
            ->first()
            ?: $rooms->sortBy('capacity')->first();
    }

    private function blockSlotIds(int $startSlotId, int $durationSlots = 1): array
    {
        $ordered = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get()->values();
        $startIndex = $ordered->search(fn ($slot) => (int) $slot->id === (int) $startSlotId);
        if ($startIndex === false || $durationSlots < 1) {
            return [];
        }

        $block = $ordered->slice($startIndex, $durationSlots)->values();
        if ($block->count() < $durationSlots || $block->contains(fn ($slot) => (bool) $slot->is_break)) {
            return [];
        }

        return $block->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function wouldCreateConsecutivePressure(array $occupied, string $type, int $id, int $day, int $slotId, int $durationSlots, int $maxConsecutive, Collection $slots): bool
    {
        $orders = $slots->pluck('sort_order', 'id');
        $targetOrder = (int) ($orders[$slotId] ?? 0);
        if ($targetOrder === 0) {
            return false;
        }

        $occupiedOrders = collect($occupied[$type][$id] ?? [])
            ->keys()
            ->map(function ($key) use ($orders, $day) {
                [$occupiedDay, $occupiedSlotId] = array_map('intval', explode('-', (string) $key) + [0, 0]);
                return $occupiedDay === $day ? (int) ($orders[$occupiedSlotId] ?? 0) : null;
            })
            ->filter()
            ->values();

        $candidateOrders = collect(range(0, max(0, $durationSlots - 1)))->map(fn (int $offset) => $targetOrder + $offset);
        $allOrders = $occupiedOrders->merge($candidateOrders)->unique()->sort()->values();
        $run = 1;
        for ($i = 1; $i < $allOrders->count(); $i++) {
            $run = ((int) $allOrders[$i] === (int) $allOrders[$i - 1] + 1) ? $run + 1 : 1;
            if ($run > $maxConsecutive) {
                return true;
            }
        }

        return false;
    }

    private function isSlotUnavailable(array $unavailable, int $day, int $slotId): bool
    {
        return collect($unavailable)->contains(fn ($blocked) => (int) ($blocked['day'] ?? 0) === $day && (int) ($blocked['slot_id'] ?? 0) === $slotId);
    }

    private function recommendedActionsForPlacementBlockers(string $primaryBlocker): array
    {
        return match ($primaryBlocker) {
            'no_candidate_rooms' => ['Add or activate a suitable room/lab, increase room capacity, or split the course group.'],
            'faculty_unavailable' => ['Adjust faculty availability, choose another faculty member, or approve a formal exception.'],
            'hard_lock_blocked' => ['Review hard locked slots for the batch, room, teacher, or group and move/remove the lock if appropriate.'],
            'incomplete_multi_slot_block' => ['Move the session to an earlier contiguous slot or configure more active non-break teaching slots.'],
            'occupied_resource_or_student' => ['Move another session, change room/faculty, or split overlapping student groups.'],
            'missing_primary_faculty' => ['Assign a primary faculty member to this course group.'],
            default => ['Review faculty, room, group, availability, and locked-slot constraints for this demand.'],
        };
    }

    private function resourceConflictBuckets(Collection $items): array
    {
        $buckets = [
            'faculty_clash' => [
                'type' => 'faculty_clash',
                'label' => 'Faculty',
                'affected_type' => 'teacher',
                'fix' => 'Move one class, change faculty, or approve a formal substitution.',
                'items' => [],
            ],
            'room_clash' => [
                'type' => 'room_clash',
                'label' => 'Room',
                'affected_type' => 'classroom',
                'fix' => 'Move one class to a different room or slot.',
                'items' => [],
            ],
            'group_clash' => [
                'type' => 'group_clash',
                'label' => 'Course group',
                'affected_type' => 'course_group',
                'fix' => 'Move one session for this section/group to a different slot.',
                'items' => [],
            ],
            'student_clash' => [
                'type' => 'student_clash',
                'label' => 'Student',
                'affected_type' => 'student',
                'fix' => 'Move one elective/core group to a different slot.',
                'items' => [],
            ],
        ];

        $scheduled = $items->where('status', 'scheduled');
        $membersByGroup = AcademicPmcCourseGroupMember::whereIn('course_group_id', $scheduled->pluck('course_group_id')->filter()->unique())
            ->where('status', 'active')
            ->get()
            ->groupBy('course_group_id');

        foreach ($scheduled as $item) {
            if (! $item->day_of_week || ! $item->timetable_slot_id) {
                continue;
            }

            $blockSlotIds = $this->blockSlotIds((int) $item->timetable_slot_id, max(1, (int) ($item->duration_slots ?? 1)));
            if (empty($blockSlotIds)) {
                $blockSlotIds = [(int) $item->timetable_slot_id];
            }

            foreach ($blockSlotIds as $slotId) {
                $key = (int) $item->day_of_week . '-' . (int) $slotId;

                if ($item->teacher_id) {
                    $buckets['faculty_clash']['items'][$key][$item->teacher_id][] = $item->id;
                }
                if ($item->classroom_id) {
                    $buckets['room_clash']['items'][$key][$item->classroom_id][] = $item->id;
                }
                if ($item->course_group_id) {
                    $buckets['group_clash']['items'][$key][$item->course_group_id][] = $item->id;
                }

                foreach ($membersByGroup->get($item->course_group_id, collect()) as $member) {
                    $buckets['student_clash']['items'][$key][$member->student_id][] = $item->id;
                }
            }
        }

        return collect($buckets)
            ->map(function (array $bucket) {
                return collect($bucket['items'])->map(function (array $resourceItems, string $key) use ($bucket) {
                    [$day, $slotId] = array_map('intval', explode('-', $key));
                    $duplicates = collect($resourceItems)
                        ->filter(fn (array $itemIds) => count(array_unique($itemIds)) > 1)
                        ->all();

                    return [
                        'type' => $bucket['type'],
                        'label' => $bucket['label'],
                        'affected_type' => $bucket['affected_type'],
                        'fix' => $bucket['fix'],
                        'day' => $day,
                        'slot_id' => $slotId,
                        'duplicates' => $duplicates,
                    ];
                })->values();
            })
            ->collapse()
            ->filter(fn (array $bucket) => ! empty($bucket['duplicates']))
            ->values()
            ->all();
    }

    private function constraint(AcademicPmcTimetableGenerationRun $run, string $type, string $severity, string $title, string $description, ?string $affectedType, ?string $affectedKey, string $fix): AcademicPmcTimetableConstraint
    {
        return AcademicPmcTimetableConstraint::create([
            'generation_run_id' => $run->id,
            'constraint_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'affected_type' => $affectedType,
            'affected_key' => $affectedKey,
            'recommended_fix' => $fix,
            'source_route' => route('academics.pmc.timetable-planner.index'),
        ]);
    }

    private function studentCompactnessScore(Collection $items): int
    {
        $score = 100;
        foreach ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->course_group_id . '-' . $item->day_of_week) as $dayItems) {
            $orders = $this->expandedSlotOrdersForItems($dayItems);
            if ($orders->count() <= 1) {
                continue;
            }
            $gaps = 0;
            for ($i = 1; $i < $orders->count(); $i++) {
                if (($orders[$i] - $orders[$i - 1]) > 1) {
                    $gaps++;
                }
            }
            $score -= $gaps * 5;
        }

        return max(40, min(100, $score));
    }

    private function dayGapCount(Collection $items): int
    {
        $orders = $this->expandedSlotOrdersForItems($items);
        if ($orders->count() <= 1) {
            return 0;
        }

        $gaps = 0;
        for ($i = 1; $i < $orders->count(); $i++) {
            if (($orders[$i] - $orders[$i - 1]) > 1) {
                $gaps += max(1, (int) ($orders[$i] - $orders[$i - 1] - 1));
            }
        }

        return $gaps;
    }

    private function expandedSlotOrdersForItems(Collection $items): Collection
    {
        $slotOrders = TimetableSlot::where('is_active', true)->pluck('sort_order', 'id');

        return $items
            ->flatMap(function ($item) use ($slotOrders) {
                if (! $item->timetable_slot_id) {
                    return [];
                }

                $block = $this->blockSlotIds((int) $item->timetable_slot_id, max(1, (int) ($item->duration_slots ?? 1)));
                if (empty($block)) {
                    $block = [(int) $item->timetable_slot_id];
                }

                return collect($block)
                    ->map(fn ($slotId) => $slotOrders[$slotId] ?? null)
                    ->filter(fn ($order) => $order !== null);
            })
            ->unique()
            ->sort()
            ->values();
    }

    private function roomUtilizationScore(Collection $items): int
    {
        $scheduled = $items->where('status', 'scheduled')->filter(fn ($item) => $item->classroom_id && $item->courseGroup);
        if ($scheduled->isEmpty()) {
            return 40;
        }

        $ratios = $scheduled->map(function ($item) {
            $capacity = max(1, (int) ($item->classroom?->capacity ?? 1));
            $strength = max(1, (int) ($item->courseGroup?->current_strength ?? 1));
            return min(1, $strength / $capacity);
        });

        return max(40, min(100, (int) round($ratios->avg() * 100)));
    }

    private function maxConsecutiveForItems(Collection $items): int
    {
        $max = 0;
        foreach ($items->groupBy('day_of_week') as $dayItems) {
            $slots = $this->expandedSlotOrdersForItems($dayItems);
            $current = 0;
            $previous = null;
            foreach ($slots as $slot) {
                $current = $previous !== null && ((int) $slot === ((int) $previous + 1)) ? $current + 1 : 1;
                $max = max($max, $current);
                $previous = $slot;
            }
        }

        return $max;
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

    public function refreshConstraintsAndQuality(AcademicPmcTimetableGenerationRun $run, callable $refreshPublishChecks): AcademicPmcTimetableQualityScore
    {
        AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->delete();
        $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->get();
        $hard = 0;
        $soft = 0;

        foreach ($this->resourceConflictBuckets($items) as $bucket) {
            foreach ($bucket['duplicates'] as $id => $duplicates) {
                $this->constraint(
                    $run,
                    $bucket['type'],
                    'hard',
                    str($bucket['type'])->headline()->toString(),
                    "{$bucket['label']} {$id} is booked more than once on day {$bucket['day']} in slot {$bucket['slot_id']}.",
                    $bucket['affected_type'],
                    (string) $id,
                    $bucket['fix']
                );
                $hard++;
            }
        }

        foreach ($items->where('status', 'unscheduled') as $item) {
            AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => 'unscheduled_class', 'severity' => 'hard', 'title' => 'Unscheduled class', 'description' => $item->explanation, 'affected_type' => 'course_group', 'affected_key' => (string) $item->course_group_id, 'recommended_fix' => 'Assign missing faculty/room or relax constraint.', 'source_route' => route('academics.pmc.timetable-generator.index')]);
            $hard++;
        }

        foreach ($items->where('status', 'scheduled') as $item) {
            $group = $item->courseGroup;
            $room = $item->classroom;
            $slot = $item->slot;
            $preference = $item->teacher_id ? AcademicPmcFacultyPreference::where('teacher_id', $item->teacher_id)->where(fn ($q) => $q->where('term_id', $group?->term_id)->orWhereNull('term_id'))->first() : null;

            if ($slot?->is_break) {
                $this->constraint($run, 'break_slot_used', 'hard', 'Break slot used for teaching', 'A teaching session was placed into a break/lunch slot.', 'timetable_slot', (string) $slot->id, 'Move this session to a non-break teaching slot.');
                $hard++;
            }

            if (($item->duration_slots ?? 1) > 1) {
                $block = $this->blockSlotIds((int) $item->timetable_slot_id, (int) $item->duration_slots);
                if (count($block) < (int) $item->duration_slots) {
                    $this->constraint($run, 'multi_slot_block_incomplete', 'hard', 'Multi-slot session lacks contiguous teaching slots', 'A lab/practical/tutorial block cannot fit into the remaining non-break slots.', 'generation_item', (string) $item->id, 'Move the session earlier or configure a valid block slot.');
                    $hard++;
                }
            }

            if ($group && $room && ($room->capacity ?? 0) > 0 && $room->capacity < $group->current_strength) {
                $this->constraint($run, 'room_capacity_mismatch', 'hard', 'Room capacity mismatch', "Room capacity {$room->capacity} is below group strength {$group->current_strength}.", 'classroom', (string) $room->id, 'Move to a larger room or split the group.');
                $hard++;
            }

            if ($group && str_contains($group->group_type, 'lab') && $room && ! ($room->has_lab || $room->type === 'lab')) {
                $this->constraint($run, 'room_type_mismatch', 'hard', 'Lab group in non-lab room', 'Lab/practical group requires a lab-capable room.', 'course_group', (string) $group->id, 'Assign a lab room or change group type.');
                $hard++;
            }

            if ($preference && $preference->available_days && ! in_array((int) $item->day_of_week, array_map('intval', $preference->available_days), true)) {
                $this->constraint($run, 'faculty_day_unavailable', 'hard', 'Faculty unavailable on scheduled day', 'Faculty is scheduled outside available teaching days.', 'teacher', (string) $item->teacher_id, 'Move class to one of the faculty available days or reassign faculty.');
                $hard++;
            }

            $unavailableSlots = collect($preference?->unavailable_slots ?: []);
            if ($unavailableSlots->contains(fn ($slot) => (int) ($slot['day'] ?? 0) === (int) $item->day_of_week && (int) ($slot['slot_id'] ?? 0) === (int) $item->timetable_slot_id)) {
                $this->constraint($run, 'faculty_slot_unavailable', 'hard', 'Faculty unavailable in slot', 'Faculty has marked this slot unavailable.', 'teacher', (string) $item->teacher_id, 'Move class or override availability with Dean/PMC approval.');
                $hard++;
            }
        }

        foreach ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->teacher_id . '-' . $item->day_of_week) as $teacherDayItems) {
            $first = $teacherDayItems->first();
            $preference = $first?->teacher_id ? AcademicPmcFacultyPreference::where('teacher_id', $first->teacher_id)->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $first->courseGroup?->term_id))->first() : null;
            $max = $preference?->max_classes_per_day ?: 4;
            if ($teacherDayItems->count() > $max) {
                $this->constraint($run, 'faculty_daily_load', 'soft', 'Faculty daily load warning', "Faculty has {$teacherDayItems->count()} classes in one day; configured max is {$max}.", 'teacher', (string) $first->teacher_id, 'Distribute classes across the week or approve overload.');
                $soft++;
            }

            $maxConsecutive = (int) ($preference?->max_consecutive_classes ?: 3);
            $consecutive = $this->maxConsecutiveForItems($teacherDayItems);
            if ($consecutive > $maxConsecutive) {
                $this->constraint($run, 'faculty_consecutive_load', 'soft', 'Faculty consecutive teaching pressure', "Faculty has {$consecutive} consecutive teaching slot(s); configured max is {$maxConsecutive}.", 'teacher', (string) $first->teacher_id, 'Move one class away from the block or approve the exception.');
                $soft++;
            }
        }

        foreach ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->course_group_id . '-' . $item->day_of_week) as $groupDayItems) {
            $first = $groupDayItems->first();
            $group = $first?->courseGroup;
            $constraints = $group?->constraints ?: [];
            $maxDaily = (int) ($constraints['max_student_classes_per_day'] ?? $constraints['max_daily_classes'] ?? 4);
            $sessionLoad = (int) $groupDayItems->sum(fn ($item) => max(1, (int) ($item->duration_slots ?? 1)));
            if ($sessionLoad > $maxDaily) {
                $this->constraint($run, 'student_group_daily_load', 'soft', 'Student group daily load pressure', "Group has {$sessionLoad} teaching slot(s) in one day; configured max is {$maxDaily}.", 'course_group', (string) $first->course_group_id, 'Spread the group classes across more days or approve a compact-day exception.');
                $soft++;
            }

            $gapCount = $this->dayGapCount($groupDayItems);
            if ($gapCount > 1) {
                $this->constraint($run, 'student_group_day_gaps', 'soft', 'Student group day has avoidable gaps', "Group has {$gapCount} empty teaching gap(s) between scheduled classes on the same day.", 'course_group', (string) $first->course_group_id, 'Move classes closer together or use a saved compact-student strategy.');
                $soft++;
            }
        }

        foreach (AcademicPmcLockedSlot::where('is_hard_lock', false)->where('status', 'active')->limit(3)->get() as $locked) {
            AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => 'soft_locked_slot_preference', 'severity' => 'soft', 'title' => 'Soft locked slot preference', 'description' => $locked->title, 'affected_type' => 'locked_slot', 'affected_key' => (string) $locked->id, 'recommended_fix' => 'Review preference before publishing.', 'source_route' => route('academics.pmc.locked-slots.index')]);
            $soft++;
        }

        $studentCompactness = $this->studentCompactnessScore($items);
        $facultyBalance = max(40, 100 - ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->teacher_id . '-' . $item->day_of_week)->filter(fn ($group) => $group->count() > 4)->count() * 12));
        $roomUtilization = $this->roomUtilizationScore($items);
        $score = max(0, min(100, (int) round((100 - ($hard * 12) - ($soft * 3) + $studentCompactness + $facultyBalance + $roomUtilization) / 4)));
        $quality = AcademicPmcTimetableQualityScore::updateOrCreate(
            ['generation_run_id' => $run->id],
            ['overall_score' => $score, 'hard_conflicts' => $hard, 'soft_warnings' => $soft, 'student_compactness_score' => $studentCompactness, 'faculty_balance_score' => $facultyBalance, 'room_utilization_score' => $roomUtilization, 'details' => ['formula' => 'avg(conflict-adjusted, student compactness, faculty balance, room utilization)', 'version' => 'PMC OS v0.063', 'faculty_consecutive_checked' => true, 'student_group_day_gaps_checked' => true]]
        );
        $run->update(['hard_conflict_count' => $hard, 'soft_warning_count' => $soft, 'quality_score' => $score]);
        $refreshPublishChecks($run, $hard, $soft, $score);
        return $quality;
    }

    public function applySolverAlternative(User $actor, AcademicPmcTimetableGenerationItem $item, int $alternativeIndex, ?string $decisionNote, bool $allowHardConflictOverride, ?string $overrideReason, callable $refreshConstraintsAndQuality, callable $audit): AcademicPmcTimetableGenerationItem
    {
        $run = $item->generationRun;
        abort_unless($run, 404, 'Generation run not found for this timetable item.');
        abort_if(in_array($run->status, ['published', 'published_with_dean_override', 'frozen', 'archived'], true), 422, 'Published or frozen timetable runs cannot be changed directly. Create a revision request instead.');

        $metadata = $item->metadata ?: [];
        $alternatives = array_values($metadata['placement_alternatives'] ?? []);
        abort_unless(isset($alternatives[$alternativeIndex]), 422, 'The selected solver alternative is no longer available.');

        $alternative = $alternatives[$alternativeIndex];
        $slotId = (int) ($alternative['slot_id'] ?? 0);
        $roomId = (int) ($alternative['room_id'] ?? 0);
        $day = (int) ($alternative['day'] ?? 0);
        abort_unless($day >= 1 && $day <= 7 && TimetableSlot::whereKey($slotId)->exists() && Classroom::whereKey($roomId)->exists(), 422, 'The selected solver alternative references an invalid day, slot, or room.');

        $beforeQuality = $refreshConstraintsAndQuality($run);
        $beforeHardConflicts = (int) $beforeQuality->hard_conflicts;
        $canOverrideHardConflict = $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);

        $previousPlacement = [
            'day' => $item->day_of_week,
            'slot_id' => $item->timetable_slot_id,
            'room_id' => $item->classroom_id,
            'confidence' => $item->confidence,
            'metadata' => $metadata,
        ];

        $item->update([
            'day_of_week' => $day,
            'timetable_slot_id' => $slotId,
            'classroom_id' => $roomId,
            'confidence' => (int) ($alternative['score'] ?? $item->confidence ?? 80),
            'is_locked' => false,
            'explanation' => 'Applied solver alternative after PMC/manual review.',
            'metadata' => array_merge($metadata, [
                'version' => 'PMC OS v0.067',
                'placement_score' => (int) ($alternative['score'] ?? $item->confidence ?? 80),
                'placement_reasons' => $alternative['reasons'] ?? [],
                'previous_placement' => $previousPlacement,
                'applied_solver_alternative' => [
                    'index' => $alternativeIndex,
                    'applied_by' => $actor->id,
                    'applied_at' => now()->toDateTimeString(),
                    'decision_note' => $decisionNote,
                    'alternative' => $alternative,
                    'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict,
                    'override_reason' => $overrideReason,
                ],
            ]),
        ]);

        $afterQuality = $refreshConstraintsAndQuality($run);
        if ((int) $afterQuality->hard_conflicts > $beforeHardConflicts) {
            if (! ($allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason))) {
                $item->update([
                    'day_of_week' => $previousPlacement['day'],
                    'timetable_slot_id' => $previousPlacement['slot_id'],
                    'classroom_id' => $previousPlacement['room_id'],
                    'confidence' => $previousPlacement['confidence'],
                    'metadata' => array_merge($previousPlacement['metadata'], [
                        'last_blocked_solver_alternative' => [
                            'index' => $alternativeIndex,
                            'blocked_at' => now()->toDateTimeString(),
                            'blocked_by' => $actor->id,
                            'reason' => 'hard_conflict_introduced',
                            'before_hard_conflicts' => $beforeHardConflicts,
                            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                            'alternative' => $alternative,
                        ],
                    ]),
                ]);
                $refreshConstraintsAndQuality($run);
                abort(422, 'Solver alternative would introduce a hard conflict. Dean/Admin override with reason is required.');
            }

            $audit($actor, 'academic_pmc_v067_solver_alternative_hard_conflict_override', 'Applied solver alternative with Dean/Admin hard-conflict override for item #' . $item->id, $item, [
                'generation_run_id' => $run->id,
                'before_hard_conflicts' => $beforeHardConflicts,
                'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                'override_reason' => $overrideReason,
                'alternative' => $alternative,
            ]);
        }

        $audit($actor, 'academic_pmc_v066_solver_alternative_applied', 'Applied solver alternative to timetable item #' . $item->id, $item, [
            'generation_run_id' => $run->id,
            'previous_placement' => $previousPlacement,
            'alternative' => $alternative,
            'decision_note' => $decisionNote,
            'before_hard_conflicts' => $beforeHardConflicts,
            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
            'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason),
        ]);

        return $item->fresh();
    }

    public function moveGeneratedItem(User $actor, AcademicPmcTimetableGenerationItem $item, array $data, bool $allowHardConflictOverride, ?string $overrideReason, callable $refreshConstraintsAndQuality, callable $audit): AcademicPmcTimetableGenerationItem
    {
        $run = $item->generationRun;
        abort_unless($run, 404, 'Generation run not found for this timetable item.');
        abort_if(in_array($run->status, ['published', 'published_with_dean_override', 'frozen', 'archived'], true), 422, 'Published or frozen timetable runs cannot be changed directly. Create a revision request instead.');

        $slotId = (int) $data['timetable_slot_id'];
        $roomId = (int) $data['classroom_id'];
        $day = (int) $data['day_of_week'];
        abort_unless($day >= 1 && $day <= 7 && TimetableSlot::whereKey($slotId)->exists() && Classroom::whereKey($roomId)->exists(), 422, 'Manual move references an invalid day, slot, or room.');

        $metadata = $item->metadata ?: [];
        $beforeQuality = $refreshConstraintsAndQuality($run);
        $beforeHardConflicts = (int) $beforeQuality->hard_conflicts;
        $canOverrideHardConflict = $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);
        $previousPlacement = [
            'day' => $item->day_of_week,
            'slot_id' => $item->timetable_slot_id,
            'room_id' => $item->classroom_id,
            'confidence' => $item->confidence,
            'metadata' => $metadata,
        ];

        $item->update([
            'day_of_week' => $day,
            'timetable_slot_id' => $slotId,
            'classroom_id' => $roomId,
            'confidence' => max(1, (int) ($item->confidence ?? 75) - 2),
            'is_locked' => false,
            'explanation' => 'Moved manually by PMC timetable review.',
            'metadata' => array_merge($metadata, [
                'version' => 'PMC OS v0.068',
                'previous_placement' => $previousPlacement,
                'manual_move' => [
                    'moved_by' => $actor->id,
                    'moved_at' => now()->toDateTimeString(),
                    'decision_note' => $data['decision_note'] ?? null,
                    'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
                    'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict,
                    'override_reason' => $overrideReason,
                ],
            ]),
        ]);

        $afterQuality = $refreshConstraintsAndQuality($run);
        if ((int) $afterQuality->hard_conflicts > $beforeHardConflicts) {
            if (! ($allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason))) {
                $item->update([
                    'day_of_week' => $previousPlacement['day'],
                    'timetable_slot_id' => $previousPlacement['slot_id'],
                    'classroom_id' => $previousPlacement['room_id'],
                    'confidence' => $previousPlacement['confidence'],
                    'metadata' => array_merge($previousPlacement['metadata'], [
                        'last_blocked_manual_move' => [
                            'blocked_at' => now()->toDateTimeString(),
                            'blocked_by' => $actor->id,
                            'reason' => 'hard_conflict_introduced',
                            'before_hard_conflicts' => $beforeHardConflicts,
                            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                            'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
                        ],
                    ]),
                ]);
                $refreshConstraintsAndQuality($run);
                abort(422, 'Manual move would introduce a hard conflict. Dean/Admin override with reason is required.');
            }

            $audit($actor, 'academic_pmc_v068_manual_move_hard_conflict_override', 'Applied manual timetable move with Dean/Admin hard-conflict override for item #' . $item->id, $item, [
                'generation_run_id' => $run->id,
                'before_hard_conflicts' => $beforeHardConflicts,
                'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                'override_reason' => $overrideReason,
                'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
            ]);
        }

        $audit($actor, 'academic_pmc_v068_manual_timetable_item_moved', 'Moved generated timetable item #' . $item->id, $item, [
            'generation_run_id' => $run->id,
            'previous_placement' => $previousPlacement,
            'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
            'decision_note' => $data['decision_note'] ?? null,
            'before_hard_conflicts' => $beforeHardConflicts,
            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
            'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason),
        ]);

        return $item->fresh();
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
