<?php
namespace App\Services;

use App\Models\TimetableEntry;

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
}
