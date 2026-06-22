<?php
namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetableConflictService {

    /**
     * Check for conflicts before saving/updating a timetable entry.
     * Returns array of conflict messages, empty array = no conflicts.
     *
     * @param array $data Keys: teacher_id, classroom_id, batch_id, day_of_week,
     *                    timetable_slot_id, term_id, program_id, exclude_id (optional)
     */
    public function check(array $data): array {
        $conflicts = [];

        $base = TimetableEntry::where('day_of_week', $data['day_of_week'])
            ->where('timetable_slot_id', $data['timetable_slot_id'])
            ->where('term_id', $data['term_id'])
            ->where('is_active', true)
            ->when(isset($data['exclude_id']), fn($q) => $q->where('id', '!=', $data['exclude_id']));

        // 1. Teacher clash
        if (!empty($data['teacher_id'])) {
            $teacherClash = (clone $base)
                ->where('teacher_id', $data['teacher_id'])
                ->with(['subject', 'batch'])
                ->first();

            if ($teacherClash) {
                $conflicts[] = "Teacher is already assigned to {$teacherClash->subject?->name}"
                    . ($teacherClash->batch ? " ({$teacherClash->batch->name})" : '')
                    . " at this slot.";
            }
        }

        // 2. Room clash
        if (!empty($data['classroom_id'])) {
            $roomClash = (clone $base)
                ->where('classroom_id', $data['classroom_id'])
                ->with(['subject', 'batch'])
                ->first();

            if ($roomClash) {
                $conflicts[] = "Room is already booked for {$roomClash->subject?->name}"
                    . ($roomClash->batch ? " ({$roomClash->batch->name})" : '')
                    . " at this slot.";
            }
        }

        // 3. Batch clash (same batch can't have two subjects at once)
        if (!empty($data['batch_id'])) {
            $batchClash = (clone $base)
                ->where('batch_id', $data['batch_id'])
                ->with('subject')
                ->first();

            if ($batchClash) {
                $conflicts[] = "This batch already has {$batchClash->subject?->name} scheduled at this slot.";
            }
        }

        return $conflicts;
    }

    /**
     * Get all conflicts for an entire timetable version / term-batch combination.
     * Returns grouped list of conflict descriptions.
     */
    public function auditTerm(int $termId, ?int $batchId = null): array {
        $canonicalItems = AcademicPmcTimetableGenerationItem::with([
            'subject',
            'teacher.user',
            'classroom',
            'batch',
            'courseGroup.members',
        ])
            ->where(function (Builder $query) use ($termId) {
                $query->where('term_id', $termId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $termId));
            })
            ->when($batchId, function (Builder $query) use ($batchId) {
                $query->where(function (Builder $scope) use ($batchId) {
                    $scope->where('batch_id', $batchId)
                        ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('batch_id', $batchId));
                });
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn (Builder $query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->get();

        if ($canonicalItems->isNotEmpty()) {
            return $this->auditCanonicalItems($canonicalItems);
        }

        $entries = TimetableEntry::where('term_id', $termId)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->where('is_active', true)
            ->with(['subject', 'teacher.user', 'classroom', 'batch'])
            ->get();

        $conflicts = [];

        // Group by day + slot to find duplicates
        $grouped = $entries->groupBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        foreach ($grouped as $key => $slotEntries) {
            if ($slotEntries->count() < 2) continue;

            // Teacher duplicates in this slot
            $teacherGroups = $slotEntries->groupBy('teacher_id');
            foreach ($teacherGroups as $teacherId => $group) {
                if ($group->count() > 1 && $teacherId) {
                    $name = $group->first()->teacher?->user?->name ?? 'Unknown';
                    $subjects = $group->pluck('subject.name')->join(', ');
                    $conflicts[] = "Teacher conflict: {$name} is assigned to {$subjects} at the same slot.";
                }
            }

            // Room duplicates
            $roomGroups = $slotEntries->groupBy('classroom_id');
            foreach ($roomGroups as $roomId => $group) {
                if ($group->count() > 1 && $roomId) {
                    $room = $group->first()->classroom?->name ?? 'Unknown room';
                    $subjects = $group->pluck('subject.name')->join(', ');
                    $conflicts[] = "Room conflict: {$room} is double-booked for {$subjects} at the same slot.";
                }
            }

            // Batch duplicates
            $batchGroups = $slotEntries->groupBy('batch_id');
            foreach ($batchGroups as $bid => $group) {
                if ($group->count() > 1 && $bid) {
                    $batchName = $group->first()->batch?->name ?? 'Unknown batch';
                    $subjects = $group->pluck('subject.name')->join(', ');
                    $conflicts[] = "Batch conflict: {$batchName} has {$subjects} scheduled at the same slot.";
                }
            }
        }

        return array_unique($conflicts);
    }

    private function auditCanonicalItems(Collection $items): array
    {
        $slotOrders = TimetableSlot::whereIn('id', $items->pluck('timetable_slot_id')->filter()->unique())
            ->pluck('sort_order', 'id');
        $conflicts = [];

        $items = $items->values();
        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                $left = $items[$i];
                $right = $items[$j];

                if ((int) $left->day_of_week !== (int) $right->day_of_week) {
                    continue;
                }

                if (! $this->canonicalItemsOverlap($left, $right, $slotOrders)) {
                    continue;
                }

                if ($left->teacher_id && $left->teacher_id === $right->teacher_id) {
                    $conflicts[] = 'Teacher conflict: ' . ($left->teacher?->user?->name ?? 'Unknown')
                        . ' is assigned to ' . $this->canonicalSubjectList($left, $right) . ' at the same time.';
                }

                if ($left->classroom_id && $left->classroom_id === $right->classroom_id) {
                    $room = $left->classroom?->room_number ?? $left->classroom?->name ?? 'Unknown room';
                    $conflicts[] = "Room conflict: {$room} is double-booked for "
                        . $this->canonicalSubjectList($left, $right) . ' at the same time.';
                }

                if ($this->canonicalStudentCohortsOverlap($left, $right)) {
                    $conflicts[] = 'Student cohort conflict: '
                        . $this->canonicalGroupName($left) . ' and ' . $this->canonicalGroupName($right)
                        . ' have overlapping students at the same time.';
                }
            }
        }

        return array_values(array_unique($conflicts));
    }

    private function canonicalItemsOverlap(AcademicPmcTimetableGenerationItem $left, AcademicPmcTimetableGenerationItem $right, Collection $slotOrders): bool
    {
        $leftStart = (int) ($slotOrders[$left->timetable_slot_id] ?? $left->timetable_slot_id);
        $rightStart = (int) ($slotOrders[$right->timetable_slot_id] ?? $right->timetable_slot_id);
        $leftEnd = $leftStart + max(1, (int) ($left->duration_slots ?? 1)) - 1;
        $rightEnd = $rightStart + max(1, (int) ($right->duration_slots ?? 1)) - 1;

        return $leftStart <= $rightEnd && $rightStart <= $leftEnd;
    }

    private function canonicalStudentCohortsOverlap(AcademicPmcTimetableGenerationItem $left, AcademicPmcTimetableGenerationItem $right): bool
    {
        if ($left->course_group_id && $left->course_group_id === $right->course_group_id) {
            return true;
        }

        $leftMembers = $left->courseGroup?->members
            ? $left->courseGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : collect();
        $rightMembers = $right->courseGroup?->members
            ? $right->courseGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : collect();

        if ($leftMembers->isNotEmpty() || $rightMembers->isNotEmpty()) {
            return $leftMembers->intersect($rightMembers)->isNotEmpty();
        }

        return ! $left->course_group_id
            && ! $right->course_group_id
            && $left->batch_id
            && $left->batch_id === $right->batch_id;
    }

    private function canonicalSubjectList(AcademicPmcTimetableGenerationItem $left, AcademicPmcTimetableGenerationItem $right): string
    {
        return collect([$left, $right])
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $item->subject?->name ?? $item->courseGroup?->subject?->name ?? 'Unknown subject')
            ->unique()
            ->join(', ');
    }

    private function canonicalGroupName(AcademicPmcTimetableGenerationItem $item): string
    {
        return $item->courseGroup?->name ?? $item->batch?->name ?? 'Unknown group';
    }

    /**
     * Check if classroom has sufficient capacity for batch.
     */
    public function checkCapacity(int $classroomId, int $batchId): array
    {
        $classroom = \App\Models\Classroom::find($classroomId);
        $batch = \App\Models\Batch::find($batchId);

        $conflicts = [];

        $batchSize = $batch ? $this->batchSize($batch) : 0;

        if ($classroom && $batch && $classroom->capacity < $batchSize) {
            $shortage = $batchSize - $classroom->capacity;
            $conflicts[] = "Capacity warning: Room {$classroom->room_number} (capacity {$classroom->capacity}) is too small for {$batch->name} ({$batchSize} students, shortage: {$shortage}).";
        }

        return $conflicts;
    }

    private function batchSize(\App\Models\Batch $batch): int
    {
        if (array_key_exists('student_count', $batch->getAttributes()) && $batch->getAttribute('student_count') !== null) {
            return (int) $batch->getAttribute('student_count');
        }

        if ($batch->relationLoaded('students') && $batch->students->count() > 0) {
            return $batch->students->count();
        }

        $studentCount = $batch->students()->count();

        return $studentCount > 0 ? $studentCount : (int) $batch->intake_capacity;
    }
}
