<?php

namespace App\Services;

use App\Models\{TimetableEntry, TimetableSlot, Teacher, Classroom, Batch};

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
            $hasConflict = TimetableEntry::where('teacher_id', $teacherId)
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
            $hasConflict = TimetableEntry::where('classroom_id', $classroomId)
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
            $hasConflict = TimetableEntry::where('batch_id', $batchId)
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
    public function isSlotAvailable(int $dayOfWeek, int $slotId, int $teacherId, int $classroomId, int $batchId, int $termId): array
    {
        $conflicts = [];

        // Check teacher
        $teacherConflict = TimetableEntry::where('teacher_id', $teacherId)
            ->where('term_id', $termId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->where('is_active', true)
            ->exists();

        if ($teacherConflict) {
            $conflicts[] = 'Teacher is already assigned to another class at this time';
        }

        // Check classroom
        $roomConflict = TimetableEntry::where('classroom_id', $classroomId)
            ->where('term_id', $termId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->where('is_active', true)
            ->exists();

        if ($roomConflict) {
            $conflicts[] = 'Classroom is already booked at this time';
        }

        // Check batch
        $batchConflict = TimetableEntry::where('batch_id', $batchId)
            ->where('term_id', $termId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->where('is_active', true)
            ->exists();

        if ($batchConflict) {
            $conflicts[] = 'Batch already has a class at this time';
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
    public function getSuggestions(int $dayOfWeek, int $slotId, int $teacherId, int $classroomId, int $batchId, int $termId): array
    {
        $suggestions = [];

        // Find alternative days for same slot
        $allDays = [1, 2, 3, 4, 5, 6];
        foreach ($allDays as $altDay) {
            if ($altDay === $dayOfWeek) continue;

            $available = $this->isSlotAvailable($altDay, $slotId, $teacherId, $classroomId, $batchId, $termId);
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

            $available = $this->isSlotAvailable($dayOfWeek, $slot->id, $teacherId, $classroomId, $batchId, $termId);
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
}
