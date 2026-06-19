<?php

namespace App\Services;

use App\Models\TimetableEntry;

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
            if ($entry->course_id == $data['course_id']) {
                $conflicts[] = "Course conflict: {$entry->course->name} already has a class in this slot.";
            }
        }

        return $conflicts;
    }

    /**
     * Build a weekly grid: day => slot_id => entry|null
     */
    public function buildWeeklyGrid(int $semesterId, ?int $courseId = null, ?int $teacherId = null, bool $officialOnly = false): array
    {
        $query = TimetableEntry::with(['subject', 'teacher.user', 'classroom', 'slot', 'course'])
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

        $entries = $query->get();
        $grid = [];

        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
        }

        foreach ($entries as $entry) {
            $grid[$entry->day_of_week][$entry->timetable_slot_id] = $entry;
        }

        return $grid;
    }

    /**
     * Get teacher's weekly load (hours per week).
     */
    public function getTeacherWeeklyLoad(int $teacherId, int $semesterId): int
    {
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

        $used = TimetableEntry::where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->count();

        return round(($used / $maxSlots) * 100, 1);
    }
}
