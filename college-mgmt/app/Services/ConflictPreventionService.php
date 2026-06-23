<?php

namespace App\Services;

use App\Models\{AcademicPmcCourseGroup, AcademicPmcFacultyPreference, AcademicPmcLockedSlot, AcademicPmcRoomCapability, AcademicPmcTimetableGenerationItem, TimetableEntry, TimetableSlot, Teacher, Classroom, Batch, TeacherAvailability};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConflictPreventionService
{
    /**
     * Get available slots for a teacher on a specific day.
     */
    public function getAvailableTeacherSlots(int $teacherId, int $dayOfWeek, int $termId): array
    {
        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availableSlots = [];

        foreach ($slots as $slot) {
            $hasConflict = $this->canonicalResourceConflict('teacher_id', $teacherId, $dayOfWeek, $slot->id, $termId)
                || TimetableEntry::where('teacher_id', $teacherId)
                ->where('term_id', $termId)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $slot->id)
                ->where('is_active', true)
                ->exists();

            if (!$hasConflict) {
                $availableSlots[] = [
                    'slot_id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'available' => true,
                ];
            }
        }

        return $availableSlots;
    }

    /**
     * Get available slots for a classroom on a specific day.
     */
    public function getAvailableClassroomSlots(int $classroomId, int $dayOfWeek, int $termId): array
    {
        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availableSlots = [];

        foreach ($slots as $slot) {
            $hasConflict = $this->canonicalResourceConflict('classroom_id', $classroomId, $dayOfWeek, $slot->id, $termId)
                || TimetableEntry::where('classroom_id', $classroomId)
                ->where('term_id', $termId)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $slot->id)
                ->where('is_active', true)
                ->exists();

            if (!$hasConflict) {
                $availableSlots[] = [
                    'slot_id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'available' => true,
                ];
            }
        }

        return $availableSlots;
    }

    /**
     * Get available slots for a batch on a specific day.
     */
    public function getAvailableBatchSlots(int $batchId, int $dayOfWeek, int $termId): array
    {
        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availableSlots = [];

        foreach ($slots as $slot) {
            $hasCanonicalItems = $this->hasCanonicalBatchItems($batchId, $termId);
            $hasConflict = $hasCanonicalItems
                ? $this->canonicalUngroupedBatchConflict($batchId, $dayOfWeek, $slot->id, $termId)
                : TimetableEntry::where('batch_id', $batchId)
                ->where('term_id', $termId)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $slot->id)
                ->where('is_active', true)
                ->exists();

            if (!$hasConflict) {
                $availableSlots[] = [
                    'slot_id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'available' => true,
                ];
            }
        }

        return $availableSlots;
    }

    /**
     * Check if a specific slot is available (no conflicts).
     */
    public function isSlotAvailable(int $dayOfWeek, int $slotId, int $teacherId, int $classroomId, int $batchId, int $termId, ?int $courseGroupId = null, int $durationSlots = 1, array $ignoreCanonicalItemIds = []): array
    {
        $conflicts = [];
        $hasCanonicalBatchItems = $this->hasCanonicalBatchItems($batchId, $termId);
        $candidateSlotIds = $this->candidateSlotIds($slotId, $durationSlots);

        $conflicts = array_merge(
            $conflicts,
            $this->canonicalFinalityConflicts($batchId, $termId, $dayOfWeek, $candidateSlotIds, $courseGroupId)
        );

        // Check teacher
        $teacherConflict = collect($candidateSlotIds)->contains(fn (int $coveredSlotId): bool =>
            $this->canonicalResourceConflict('teacher_id', $teacherId, $dayOfWeek, $coveredSlotId, $termId, $ignoreCanonicalItemIds)
            || TimetableEntry::where('teacher_id', $teacherId)
                ->where('term_id', $termId)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $coveredSlotId)
                ->where('is_active', true)
                ->exists()
        );

        if ($teacherConflict) {
            $conflicts[] = 'Teacher is already assigned to another class at this time';
        }

        $conflicts = array_merge(
            $conflicts,
            $this->teacherAvailabilityConflicts($teacherId, $termId, $dayOfWeek, $candidateSlotIds)
        );

        $conflicts = array_merge(
            $conflicts,
            $this->lockedSlotConflicts($teacherId, $classroomId, $batchId, $termId, $dayOfWeek, $candidateSlotIds, $courseGroupId)
        );

        // Check classroom
        $roomConflict = collect($candidateSlotIds)->contains(fn (int $coveredSlotId): bool =>
            $this->canonicalResourceConflict('classroom_id', $classroomId, $dayOfWeek, $coveredSlotId, $termId, $ignoreCanonicalItemIds)
            || TimetableEntry::where('classroom_id', $classroomId)
                ->where('term_id', $termId)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $coveredSlotId)
                ->where('is_active', true)
                ->exists()
        );

        if ($roomConflict) {
            $conflicts[] = 'Classroom is already booked at this time';
        }

        if ($courseGroupId) {
            $conflicts = array_merge($conflicts, $this->canonicalRoomSuitabilityConflicts($courseGroupId, $classroomId));
        }

        // Check student cohort, not the whole batch, when canonical group data exists.
        $cohortConflict = collect($candidateSlotIds)->contains(fn (int $coveredSlotId): bool =>
            $courseGroupId
                ? $this->canonicalCourseGroupConflict($courseGroupId, $dayOfWeek, $coveredSlotId, $termId, $ignoreCanonicalItemIds)
                : ($hasCanonicalBatchItems ? $this->canonicalUngroupedBatchConflict($batchId, $dayOfWeek, $coveredSlotId, $termId, $ignoreCanonicalItemIds) : false)
        );

        $batchConflict = ! $hasCanonicalBatchItems && collect($candidateSlotIds)->contains(fn (int $coveredSlotId): bool =>
            TimetableEntry::where('batch_id', $batchId)
                ->where('term_id', $termId)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $coveredSlotId)
                ->where('is_active', true)
                ->exists()
        );

        if ($cohortConflict || $batchConflict) {
            $conflicts[] = $courseGroupId
                ? 'Student group has an overlapping class at this time'
                : 'Batch already has a class at this time';
        }

        return [
            'available' => empty($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Get next available slot for a teacher (across all days).
     */
    public function getNextAvailableSlotForTeacher(int $teacherId, int $termId, int $preferredDayStart = 1): array
    {
        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Try each day starting from preferred day
        for ($day = $preferredDayStart; $day <= 6; $day++) {
            foreach ($slots as $slot) {
                $hasConflict = TimetableEntry::where('teacher_id', $teacherId)
                    ->where('term_id', $termId)
                    ->where('day_of_week', $day)
                    ->where('timetable_slot_id', $slot->id)
                    ->where('is_active', true)
                    ->exists();

                if (!$hasConflict) {
                    return [
                        'available' => true,
                        'day_of_week' => $day,
                        'day_name' => $this->getDayName($day),
                        'slot_id' => $slot->id,
                        'slot_name' => $slot->name,
                    ];
                }
            }
        }

        return ['available' => false];
    }

    /**
     * Get alternative days/slots if a specific combination has conflicts.
     */
    public function getSuggestions(int $dayOfWeek, int $slotId, int $teacherId, int $classroomId, int $batchId, int $termId, ?int $courseGroupId = null): array
    {
        $suggestions = [];

        // Find alternative days for same slot
        $allDays = [1, 2, 3, 4, 5, 6];
        foreach ($allDays as $altDay) {
            if ($altDay === $dayOfWeek) continue;

            $available = $this->isSlotAvailable($altDay, $slotId, $teacherId, $classroomId, $batchId, $termId, $courseGroupId);
            if ($available['available']) {
                $suggestions[] = [
                    'type' => 'same_slot_different_day',
                    'day' => $altDay,
                    'day_name' => $this->getDayName($altDay),
                    'slot' => $slotId,
                    'reason' => 'Same time, different day',
                ];
            }
        }

        // Find alternative slots for same day
        $slots = TimetableSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($slots as $slot) {
            if ($slot->id === $slotId) continue;

            $available = $this->isSlotAvailable($dayOfWeek, $slot->id, $teacherId, $classroomId, $batchId, $termId, $courseGroupId);
            if ($available['available']) {
                $suggestions[] = [
                    'type' => 'same_day_different_slot',
                    'day' => $dayOfWeek,
                    'day_name' => $this->getDayName($dayOfWeek),
                    'slot' => $slot->id,
                    'slot_name' => $slot->name,
                    'reason' => 'Same day, different time',
                ];
            }
        }

        return array_slice($suggestions, 0, 5); // Top 5 suggestions
    }

    private function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        return $days[$day] ?? 'Unknown';
    }

    private function canonicalResourceConflict(string $column, int $resourceId, int $dayOfWeek, int $slotId, int $termId, array $ignoreCanonicalItemIds = []): bool
    {
        return $this->canonicalItemsForDay($termId, $dayOfWeek, $ignoreCanonicalItemIds)
            ->where($column, $resourceId)
            ->contains(fn (AcademicPmcTimetableGenerationItem $item) => $this->itemOverlapsSlot($item, $slotId));
    }

    private function canonicalCourseGroupConflict(int $courseGroupId, int $dayOfWeek, int $slotId, int $termId, array $ignoreCanonicalItemIds = []): bool
    {
        $candidate = AcademicPmcCourseGroup::with('members')->find($courseGroupId);
        if (! $candidate) {
            return false;
        }

        $candidateMembers = $candidate->members
            ->where('status', 'active')
            ->pluck('student_id')
            ->filter()
            ->unique();

        return $this->canonicalItemsForDay($termId, $dayOfWeek, $ignoreCanonicalItemIds)
            ->filter(fn (AcademicPmcTimetableGenerationItem $item) => $this->itemOverlapsSlot($item, $slotId))
            ->contains(function (AcademicPmcTimetableGenerationItem $item) use ($courseGroupId, $candidateMembers) {
                if ($item->course_group_id === $courseGroupId) {
                    return true;
                }

                $itemMembers = $item->courseGroup?->members
                    ? $item->courseGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
                    : collect();

                if ($candidateMembers->isNotEmpty() || $itemMembers->isNotEmpty()) {
                    return $candidateMembers->intersect($itemMembers)->isNotEmpty();
                }

                return false;
            });
    }

    private function canonicalUngroupedBatchConflict(int $batchId, int $dayOfWeek, int $slotId, int $termId, array $ignoreCanonicalItemIds = []): bool
    {
        return $this->canonicalItemsForDay($termId, $dayOfWeek, $ignoreCanonicalItemIds)
            ->where('batch_id', $batchId)
            ->whereNull('course_group_id')
            ->contains(fn (AcademicPmcTimetableGenerationItem $item) => $this->itemOverlapsSlot($item, $slotId));
    }

    private function canonicalRoomSuitabilityConflicts(int $courseGroupId, int $classroomId): array
    {
        $group = AcademicPmcCourseGroup::with('members')->find($courseGroupId);
        $classroom = Classroom::find($classroomId);

        if (! $group || ! $classroom) {
            return [];
        }

        $conflicts = [];
        $groupSize = $this->courseGroupSize($group);

        if ((int) ($classroom->capacity ?? 0) < $groupSize) {
            $conflicts[] = "Classroom capacity is too small for this student group ({$groupSize} students)";
        }

        if ($this->requiresLabRoom($group) && ! $this->isLabRoom($classroom)) {
            $conflicts[] = 'Lab/practical group requires a lab-capable room';
        }

        return $conflicts;
    }

    private function teacherAvailabilityConflicts(int $teacherId, int $termId, int $dayOfWeek, array $slotIds): array
    {
        $conflicts = [];

        $explicitUnavailable = TeacherAvailability::where('teacher_id', $teacherId)
            ->where('term_id', $termId)
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('timetable_slot_id', $slotIds)
            ->where('availability', 'unavailable')
            ->exists();

        if ($explicitUnavailable) {
            $conflicts[] = 'Teacher is marked unavailable at this time';
        }

        $preference = AcademicPmcFacultyPreference::where('teacher_id', $teacherId)
            ->where(fn (Builder $query) => $query->where('term_id', $termId)->orWhereNull('term_id'))
            ->orderByRaw('CASE WHEN term_id IS NULL THEN 1 ELSE 0 END')
            ->latest()
            ->first();

        if (! $preference) {
            return $conflicts;
        }

        $availableDays = array_values(array_filter(array_map('intval', $preference->available_days ?: [])));
        if ($availableDays !== [] && ! in_array($dayOfWeek, $availableDays, true)) {
            $conflicts[] = 'Teacher is not available on this day';
        }

        foreach ($slotIds as $slotId) {
            if ($this->isPreferenceSlotUnavailable($preference->unavailable_slots ?: [], $dayOfWeek, (int) $slotId)) {
                $conflicts[] = 'Teacher preference marks this slot unavailable';
                break;
            }
        }

        return array_values(array_unique($conflicts));
    }

    private function isPreferenceSlotUnavailable(array $unavailableSlots, int $dayOfWeek, int $slotId): bool
    {
        foreach ($unavailableSlots as $key => $value) {
            if (is_array($value) && (array_key_exists('day', $value) || array_key_exists('day_of_week', $value))) {
                $blockedDay = (int) ($value['day'] ?? $value['day_of_week']);
                $blockedSlot = (int) ($value['slot_id'] ?? $value['slot'] ?? 0);

                if ($blockedDay === $dayOfWeek && $blockedSlot === $slotId) {
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

    private function lockedSlotConflicts(int $teacherId, int $classroomId, int $batchId, int $termId, int $dayOfWeek, array $slotIds, ?int $courseGroupId): array
    {
        $conflicts = [];
        $candidateGroup = $courseGroupId ? AcademicPmcCourseGroup::with('members')->find($courseGroupId) : null;
        $candidateProgramId = (int) ($candidateGroup?->program_id ?? Batch::find($batchId)?->program_id ?? 0);
        $candidateMembers = $candidateGroup
            ? $candidateGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : collect();

        $locks = AcademicPmcLockedSlot::with('courseGroup.members')
            ->where('status', 'active')
            ->where('is_hard_lock', true)
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('timetable_slot_id', $slotIds)
            ->where(fn (Builder $query) => $query->where('term_id', $termId)->orWhereNull('term_id'))
            ->get();

        foreach ($locks as $lock) {
            if ($courseGroupId && (int) $lock->course_group_id === $courseGroupId) {
                if ($lock->teacher_id && (int) $lock->teacher_id !== $teacherId) {
                    $conflicts[] = 'Hard locked slot requires a different teacher';
                }

                if ($lock->classroom_id && (int) $lock->classroom_id !== $classroomId) {
                    $conflicts[] = 'Hard locked slot requires a different classroom';
                }

                continue;
            }

            if ($lock->teacher_id && (int) $lock->teacher_id === $teacherId) {
                $conflicts[] = 'Teacher has a hard locked slot at this time';
            }

            if ($lock->classroom_id && (int) $lock->classroom_id === $classroomId) {
                $conflicts[] = 'Classroom has a hard locked slot at this time';
            }

            if ($this->lockedSlotBlocksCandidateGroup($lock, $candidateGroup, $candidateMembers)) {
                $conflicts[] = 'Student group has a hard locked slot at this time';
            }

            if ($this->lockedSlotBlocksCandidateScope($lock, $batchId, $candidateProgramId)) {
                $conflicts[] = 'Institutional slot is hard locked for this scope';
            }
        }

        return array_values(array_unique($conflicts));
    }

    private function lockedSlotBlocksCandidateGroup(AcademicPmcLockedSlot $lock, ?AcademicPmcCourseGroup $candidateGroup, Collection $candidateMembers): bool
    {
        if (! $candidateGroup || ! $lock->course_group_id || (int) $lock->course_group_id === (int) $candidateGroup->id) {
            return false;
        }

        $lockedMembers = $lock->courseGroup?->members
            ? $lock->courseGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : collect();

        return $candidateMembers->isNotEmpty() && $lockedMembers->isNotEmpty() && $candidateMembers->intersect($lockedMembers)->isNotEmpty();
    }

    private function lockedSlotBlocksCandidateScope(AcademicPmcLockedSlot $lock, int $batchId, int $programId): bool
    {
        if ($lock->course_group_id) {
            return false;
        }

        if ($lock->batch_id && (int) $lock->batch_id === $batchId) {
            return true;
        }

        if (! $lock->batch_id && $lock->program_id && $programId && (int) $lock->program_id === $programId) {
            return true;
        }

        return ! $lock->program_id
            && ! $lock->batch_id
            && ! $lock->teacher_id
            && ! $lock->classroom_id;
    }

    private function canonicalFinalityConflicts(int $batchId, int $termId, int $dayOfWeek, array $slotIds, ?int $courseGroupId): array
    {
        $finalItems = $this->canonicalItemsForDay($termId, $dayOfWeek)
            ->filter(fn (AcademicPmcTimetableGenerationItem $item): bool =>
                collect($slotIds)->contains(fn (int $slotId): bool => $this->itemOverlapsSlot($item, $slotId))
            )
            ->filter(fn (AcademicPmcTimetableGenerationItem $item): bool => $this->isFinalCanonicalItem($item));

        $blocksCandidate = $courseGroupId
            ? $finalItems->contains(fn (AcademicPmcTimetableGenerationItem $item): bool => (int) $item->course_group_id === $courseGroupId)
            : $finalItems->contains(fn (AcademicPmcTimetableGenerationItem $item): bool =>
                (int) $item->batch_id === $batchId && blank($item->course_group_id)
            );

        return $blocksCandidate
            ? ['Published or locked canonical timetable session cannot be edited directly']
            : [];
    }

    private function isFinalCanonicalItem(AcademicPmcTimetableGenerationItem $item): bool
    {
        return in_array((string) $item->official_status, ['published', 'locked'], true)
            || in_array((string) $item->status, ['published', 'locked'], true)
            || (bool) $item->is_locked
            || $item->timetableVersion?->status === 'published';
    }

    private function courseGroupSize(AcademicPmcCourseGroup $group): int
    {
        $memberCount = $group->relationLoaded('members')
            ? $group->members->where('status', 'active')->count()
            : $group->members()->where('status', 'active')->count();

        return max(1, $memberCount, (int) ($group->current_strength ?? 0));
    }

    private function requiresLabRoom(AcademicPmcCourseGroup $group): bool
    {
        return str_contains((string) $group->group_type, 'lab');
    }

    private function isLabRoom(Classroom $classroom): bool
    {
        return (bool) ($classroom->has_lab ?? false)
            || strtolower((string) $classroom->type) === 'lab'
            || AcademicPmcRoomCapability::where('classroom_id', $classroom->id)
                ->where('is_active', true)
                ->where('capability_type', 'lab')
                ->exists();
    }

    private function hasCanonicalBatchItems(int $batchId, int $termId): bool
    {
        return AcademicPmcTimetableGenerationItem::query()
            ->where(function (Builder $query) use ($batchId, $termId) {
                $query->where(function (Builder $direct) use ($batchId, $termId) {
                    $direct->where('batch_id', $batchId)
                        ->where('term_id', $termId);
                })->orWhereHas('courseGroup', function (Builder $group) use ($batchId, $termId) {
                    $group->where('batch_id', $batchId)
                        ->where('term_id', $termId);
                });
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn (Builder $query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->exists();
    }

    private function canonicalItemsForDay(int $termId, int $dayOfWeek, array $ignoreCanonicalItemIds = []): Collection
    {
        return AcademicPmcTimetableGenerationItem::with(['courseGroup.members', 'timetableVersion'])
            ->where(function (Builder $query) use ($termId) {
                $query->where('term_id', $termId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $termId));
            })
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn (Builder $query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->when($ignoreCanonicalItemIds !== [], fn (Builder $query) => $query->whereNotIn('id', $ignoreCanonicalItemIds))
            ->get();
    }

    private function candidateSlotIds(int $slotId, int $durationSlots): array
    {
        $durationSlots = max(1, $durationSlots);
        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get(['id', 'sort_order']);
        $start = $slots->firstWhere('id', $slotId);

        if (! $start) {
            return [$slotId];
        }

        return $slots
            ->filter(fn (TimetableSlot $slot): bool =>
                $slot->sort_order >= $start->sort_order
                && $slot->sort_order < $start->sort_order + $durationSlots
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function itemOverlapsSlot(AcademicPmcTimetableGenerationItem $item, int $slotId): bool
    {
        $slotOrders = TimetableSlot::whereIn('id', [$item->timetable_slot_id, $slotId])->pluck('sort_order', 'id');
        $itemStart = (int) ($slotOrders[$item->timetable_slot_id] ?? $item->timetable_slot_id);
        $target = (int) ($slotOrders[$slotId] ?? $slotId);
        $itemEnd = $itemStart + max(1, (int) ($item->duration_slots ?? 1)) - 1;

        return $itemStart <= $target && $target <= $itemEnd;
    }
}
