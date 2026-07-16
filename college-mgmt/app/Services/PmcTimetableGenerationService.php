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
