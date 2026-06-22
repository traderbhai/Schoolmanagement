<?php

namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\Course;
use App\Models\Semester;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetableService
{
    /**
     * Check for all conflicts before creating/updating an entry.
     * Returns array of conflict messages, empty if none.
     */
    public function checkConflicts(array $data, ?int $excludeId = null): array
    {
        $conflicts = [];
        $query = TimetableEntry::where('semester_id', $data['semester_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('timetable_slot_id', $data['timetable_slot_id'])
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existing = $query->get();

        foreach ($existing as $entry) {
            if ($entry->classroom_id == $data['classroom_id']) {
                $conflicts[] = "Room conflict: {$entry->classroom->room_number} is already booked for this slot.";
            }
            if ($entry->teacher_id == $data['teacher_id']) {
                $conflicts[] = "Teacher conflict: {$entry->teacher->user->name} is already teaching at this time.";
            }
        }

        foreach ($this->canonicalOfficialItemsForConflict($data) as $item) {
            if (! $this->canonicalItemOverlapsSlot($item, (int) $data['timetable_slot_id'])) {
                continue;
            }

            if ((int) $item->classroom_id === (int) $data['classroom_id']) {
                $room = $item->classroom?->room_number ?? $item->classroom?->name ?? 'Room';
                $conflicts[] = "Room conflict: {$room} is already booked for an official PMC session at this slot.";
            }

            if ((int) $item->teacher_id === (int) $data['teacher_id']) {
                $teacher = $item->teacher?->user?->name ?? 'Teacher';
                $conflicts[] = "Teacher conflict: {$teacher} is already teaching an official PMC session at this time.";
            }
        }

        return array_values(array_unique($conflicts));
    }

    /**
     * Build a weekly grid: day => slot_id => Collection<entry>
     */
    public function buildWeeklyGrid(int $semesterId, ?int $courseId = null, ?int $teacherId = null, bool $officialOnly = false): array
    {
        $grid = [];

        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
        }

        $canonicalItems = $officialOnly
            ? $this->officialPmcItemsForSemester($semesterId, $courseId, $teacherId)
            : collect();

        foreach ($canonicalItems as $item) {
            foreach ($this->coveredSlotIdsForItem($item) as $coveredSlotId) {
                $grid[$item->day_of_week][$coveredSlotId] ??= collect();
                $grid[$item->day_of_week][$coveredSlotId]->push($this->displayEntryFromPmcItem($item, $coveredSlotId));
            }
        }

        $canonicalProgramTermKeys = $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->canonicalProgramTermKey($item))
            ->filter()
            ->unique()
            ->values();

        $query = TimetableEntry::with(['subject', 'teacher.user', 'classroom', 'slot', 'course', 'pmcGenerationItem.courseGroup'])
            ->where('semester_id', $semesterId)
            ->where('is_active', true);

        if ($officialOnly) {
            $query->where('status', 'published')
                ->where(function ($versionQuery) {
                    $versionQuery->whereNull('timetable_version_id')
                        ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
                });
        }

        if ($courseId) {
            $query->where('course_id', $courseId);
        }
        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $entries = $query->get()
            ->reject(fn (TimetableEntry $entry) => $canonicalProgramTermKeys->contains($this->programTermKey($entry->program_id, $entry->term_id)));

        foreach ($entries as $entry) {
            $grid[$entry->day_of_week][$entry->timetable_slot_id] ??= collect();
            $grid[$entry->day_of_week][$entry->timetable_slot_id]->push($this->displayEntryFromLegacyEntry($entry));
        }

        return $grid;
    }

    /**
     * Get teacher's weekly load (hours per week).
     */
    public function getTeacherWeeklyLoad(int $teacherId, int $semesterId): int
    {
        $canonicalLoad = $this->canonicalOfficialTeacherSlotUse($teacherId, $semesterId);
        if ($canonicalLoad > 0) {
            return $canonicalLoad;
        }

        return TimetableEntry::where('teacher_id', $teacherId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get classroom utilization percentage for a semester.
     */
    public function getClassroomUtilization(int $classroomId, int $semesterId): float
    {
        $totalSlots = \App\Models\TimetableSlot::where('is_active', true)->where('is_break', false)->count();
        $workingDays = 6;
        $maxSlots = $totalSlots * $workingDays;

        if ($maxSlots === 0) return 0;

        $canonicalUsed = $this->canonicalOfficialRoomSlotUse($classroomId, $semesterId);
        if ($canonicalUsed > 0) {
            return round(($canonicalUsed / $maxSlots) * 100, 1);
        }

        $used = TimetableEntry::where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->count();

        return round(($used / $maxSlots) * 100, 1);
    }

    private function canonicalOfficialRoomSlotUse(int $classroomId, int $semesterId): int
    {
        $termIds = $this->termIdsForSemester($semesterId);
        if ($termIds === []) {
            return 0;
        }

        return (int) AcademicPmcTimetableGenerationItem::query()
            ->where('classroom_id', $classroomId)
            ->where(function (Builder $query) use ($termIds) {
                $query->whereIn('term_id', $termIds)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->whereIn('term_id', $termIds));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->get()
            ->sum(fn ($item) => max(1, (int) ($item->duration_slots ?? 1)));
    }

    private function canonicalOfficialTeacherSlotUse(int $teacherId, int $semesterId): int
    {
        $termIds = $this->termIdsForSemester($semesterId);
        if ($termIds === []) {
            return 0;
        }

        return (int) AcademicPmcTimetableGenerationItem::query()
            ->where('teacher_id', $teacherId)
            ->where(function (Builder $query) use ($termIds) {
                $query->whereIn('term_id', $termIds)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->whereIn('term_id', $termIds));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->get()
            ->sum(fn ($item) => max(1, (int) ($item->duration_slots ?? 1)));
    }

    private function canonicalOfficialItemsForConflict(array $data): Collection
    {
        $termIds = $this->termIdsForSemester((int) $data['semester_id']);
        if ($termIds === []) {
            return collect();
        }

        return AcademicPmcTimetableGenerationItem::with(['teacher.user', 'classroom', 'timetableVersion'])
            ->where('day_of_week', (int) $data['day_of_week'])
            ->where(function (Builder $query) use ($termIds) {
                $query->whereIn('term_id', $termIds)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->whereIn('term_id', $termIds));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->where(function (Builder $query) use ($data) {
                $query->where('classroom_id', (int) $data['classroom_id'])
                    ->orWhere('teacher_id', (int) $data['teacher_id']);
            })
            ->get();
    }

    private function canonicalItemOverlapsSlot(AcademicPmcTimetableGenerationItem $item, int $slotId): bool
    {
        $slotOrders = \App\Models\TimetableSlot::whereIn('id', [$item->timetable_slot_id, $slotId])->pluck('sort_order', 'id');
        $itemStart = (int) ($slotOrders[$item->timetable_slot_id] ?? $item->timetable_slot_id);
        $target = (int) ($slotOrders[$slotId] ?? $slotId);
        $itemEnd = $itemStart + max(1, (int) ($item->duration_slots ?? 1)) - 1;

        return $itemStart <= $target && $target <= $itemEnd;
    }

    private function termIdsForSemester(int $semesterId): array
    {
        $semester = Semester::find($semesterId);
        if (! $semester) {
            return [];
        }

        return Term::where(fn ($query) => $query->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function officialPmcItemsForSemester(int $semesterId, ?int $courseId = null, ?int $teacherId = null): Collection
    {
        $termIds = $this->termIdsForSemester($semesterId);
        if ($termIds === []) {
            return collect();
        }

        $pmcBridgeScope = $this->pmcBridgeScopeForCourse($courseId);

        return AcademicPmcTimetableGenerationItem::with([
            'subject',
            'teacher.user',
            'classroom',
            'slot',
            'courseGroup.subject',
            'courseGroup.batch',
            'batch',
            'timetableVersion',
        ])
            ->where(function (Builder $query) use ($termIds) {
                $query->whereIn('term_id', $termIds)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->whereIn('term_id', $termIds));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->when($teacherId, fn (Builder $query) => $query->where('teacher_id', $teacherId))
            ->when($pmcBridgeScope, function (Builder $query, array $scope) {
                $scope['type'] === 'group'
                    ? $query->where('course_group_id', $scope['id'])
                    : $query->whereNull('course_group_id')->whereKey($scope['id']);
            })
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->orderBy('course_group_id')
            ->get();
    }

    private function pmcBridgeScopeForCourse(?int $courseId): ?array
    {
        if (! $courseId) {
            return null;
        }

        $code = Course::whereKey($courseId)->value('code');
        if (! is_string($code)) {
            return null;
        }

        if (preg_match('/^PMCG(\d+)$/', $code, $matches)) {
            return ['type' => 'group', 'id' => (int) $matches[1]];
        }

        if (preg_match('/^PMCS(\d+)$/', $code, $matches)) {
            return ['type' => 'session', 'id' => (int) $matches[1]];
        }

        return null;
    }

    private function coveredSlotIdsForItem(AcademicPmcTimetableGenerationItem $item): array
    {
        $duration = max(1, (int) ($item->duration_slots ?? 1));
        if ($duration === 1) {
            return [(int) $item->timetable_slot_id];
        }

        $slots = TimetableSlot::query()
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get(['id', 'sort_order']);

        $startOrder = $slots->firstWhere('id', (int) $item->timetable_slot_id)?->sort_order;
        if ($startOrder === null) {
            return [(int) $item->timetable_slot_id];
        }

        $covered = $slots
            ->filter(fn (TimetableSlot $slot) => (int) $slot->sort_order >= (int) $startOrder
                && (int) $slot->sort_order < ((int) $startOrder + $duration))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $covered !== [] ? $covered : [(int) $item->timetable_slot_id];
    }

    private function displayEntryFromPmcItem(AcademicPmcTimetableGenerationItem $item, ?int $coveredSlotId = null): object
    {
        $subject = $item->subject ?? $item->courseGroup?->subject;
        $coveredSlotId ??= (int) $item->timetable_slot_id;

        return (object) [
            'id' => null,
            'source' => 'canonical_pmc_official_session',
            'pmc_generation_item_id' => $item->id,
            'subject' => $subject,
            'teacher' => $item->teacher,
            'classroom' => $item->classroom,
            'slot' => $item->slot,
            'course' => null,
            'course_group' => $item->courseGroup,
            'day_of_week' => $item->day_of_week,
            'timetable_slot_id' => $item->timetable_slot_id,
            'covered_timetable_slot_id' => $coveredSlotId,
            'duration_slots' => max(1, (int) ($item->duration_slots ?? 1)),
            'is_continuation' => (int) $coveredSlotId !== (int) $item->timetable_slot_id,
        ];
    }

    private function displayEntryFromLegacyEntry(TimetableEntry $entry): object
    {
        return (object) [
            'id' => $entry->id,
            'source' => 'legacy_timetable_entry',
            'pmc_generation_item_id' => $entry->pmc_generation_item_id,
            'subject' => $entry->subject,
            'teacher' => $entry->teacher,
            'classroom' => $entry->classroom,
            'slot' => $entry->slot,
            'course' => $entry->course,
            'course_group' => $entry->pmcGenerationItem?->courseGroup,
            'day_of_week' => $entry->day_of_week,
            'timetable_slot_id' => $entry->timetable_slot_id,
            'duration_slots' => 1,
        ];
    }

    private function programTermKey(?int $programId, ?int $termId): ?string
    {
        if (! $programId || ! $termId) {
            return null;
        }

        return $programId . ':' . $termId;
    }

    private function canonicalProgramTermKey(AcademicPmcTimetableGenerationItem $item): ?string
    {
        return $this->programTermKey(
            $item->program_id ?? $item->courseGroup?->program_id,
            $item->term_id ?? $item->courseGroup?->term_id
        );
    }
}
