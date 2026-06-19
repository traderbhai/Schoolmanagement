<?php

namespace App\Services;

use App\Models\{TimetableEntry, TimetableSlot, Teacher, Term};
use Illuminate\Database\Eloquent\Builder;

class TeacherWorkloadWarningService
{
    const OPTIMAL_MIN = 6;
    const OPTIMAL_MAX = 18;
    const WARNING_THRESHOLD = 15; // Show warning at 15+ hrs/week

    /**
     * Get current workload for a teacher in a term.
     */
    public function getCurrentWorkload(int $teacherId, int $termId): array
    {
        $entries = TimetableEntry::where('teacher_id', $teacherId)
            ->where('term_id', $termId)
            ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
            ->with(['slot', 'subject', 'batch'])
            ->get();

        $totalHours = 0;
        foreach ($entries as $entry) {
            $totalHours += $this->getSlotDuration($entry->timetable_slot_id);
        }

        $teachingWeeks = $this->getTeachingWeeks($termId);
        $weeklyLoad = $teachingWeeks > 0 ? $totalHours / $teachingWeeks : 0;

        return [
            'teacher_id' => $teacherId,
            'total_hours' => $totalHours,
            'weekly_load' => round($weeklyLoad, 2),
            'session_count' => $entries->count(),
            'subject_count' => $entries->pluck('subject_id')->unique()->count(),
            'entries' => $entries->map(fn($e) => [
                'day' => $e->day_of_week,
                'slot' => $e->slot->name ?? 'N/A',
                'subject' => $e->subject->name ?? 'N/A',
                'batch' => $e->batch->name ?? 'N/A',
                'hours' => $this->getSlotDuration($e->timetable_slot_id),
            ])->toArray(),
        ];
    }

    /**
     * Get workload warning for a new assignment.
     */
    public function getAssignmentWarning(int $teacherId, int $termId, int $newSlotId): array
    {
        $current = $this->getCurrentWorkload($teacherId, $termId);
        $newHours = $this->getSlotDuration($newSlotId);
        $projectedTotal = $current['total_hours'] + $newHours;

        $teachingWeeks = $this->getTeachingWeeks($termId);
        $projectedWeekly = $teachingWeeks > 0 ? $projectedTotal / $teachingWeeks : 0;

        $warning = [
            'current_weekly' => $current['weekly_load'],
            'new_session_hours' => $newHours,
            'projected_weekly' => round($projectedWeekly, 2),
            'projected_total' => $projectedTotal,
            'has_warning' => false,
            'warning_type' => null,
            'message' => '',
        ];

        if ($projectedWeekly > self::OPTIMAL_MAX) {
            $warning['has_warning'] = true;
            $warning['warning_type'] = 'overload';
            $excess = round($projectedWeekly - self::OPTIMAL_MAX, 2);
            $warning['message'] = "⚠️ Assignment will EXCEED optimal workload. Projected load: {$projectedWeekly} hrs/week (excess: {$excess} hrs)";
        } elseif ($projectedWeekly > self::WARNING_THRESHOLD) {
            $warning['has_warning'] = true;
            $warning['warning_type'] = 'approaching';
            $remaining = round(self::OPTIMAL_MAX - $projectedWeekly, 2);
            $warning['message'] = "⚠️ Teacher is approaching overload. Projected: {$projectedWeekly} hrs/week (remaining capacity: {$remaining} hrs)";
        } else {
            $warning['message'] = "✓ Workload is acceptable. Projected: {$projectedWeekly} hrs/week";
        }

        return $warning;
    }

    /**
     * Get all teachers with workload warnings in a term.
     */
    public function getTeachersWithWarnings(int $termId): array
    {
        $entries = TimetableEntry::where('term_id', $termId)
            ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
            ->with(['teacher.user', 'slot'])
            ->get()
            ->groupBy('teacher_id');

        $warnings = [];

        foreach ($entries as $teacherId => $teacherEntries) {
            $totalHours = $teacherEntries->sum(fn($e) => $this->getSlotDuration($e->timetable_slot_id));
            $teachingWeeks = $this->getTeachingWeeks($termId);
            $weeklyLoad = $teachingWeeks > 0 ? $totalHours / $teachingWeeks : 0;

            if ($weeklyLoad >= self::WARNING_THRESHOLD) {
                $teacher = $teacherEntries->first()->teacher;
                $warnings[] = [
                    'teacher_id' => $teacherId,
                    'teacher_name' => $teacher?->user->name ?? 'Unknown',
                    'weekly_load' => round($weeklyLoad, 2),
                    'session_count' => $teacherEntries->count(),
                    'warning_type' => $weeklyLoad > self::OPTIMAL_MAX ? 'overload' : 'approaching',
                    'status_color' => $weeklyLoad > self::OPTIMAL_MAX ? 'danger' : 'warning',
                ];
            }
        }

        usort($warnings, fn($a, $b) => $b['weekly_load'] <=> $a['weekly_load']);

        return $warnings;
    }

    /**
     * Suggest alternative teachers with lighter workload.
     */
    public function suggestAlternativeTeachers(int $termId, int $currentTeacherId, int $departmentId): array
    {
        // Get teachers in same department
        $teachers = Teacher::where('department_id', $departmentId)
            ->where('status', 'active')
            ->where('id', '!=', $currentTeacherId)
            ->with('user')
            ->get();

        $suggestions = [];

        foreach ($teachers as $teacher) {
            $workload = $this->getCurrentWorkload($teacher->id, $termId);
            $weeklyLoad = $workload['weekly_load'];

            if ($weeklyLoad <= self::OPTIMAL_MAX) {
                $suggestions[] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->user->name,
                    'current_weekly_load' => round($weeklyLoad, 2),
                    'remaining_capacity' => round(self::OPTIMAL_MAX - $weeklyLoad, 2),
                    'session_count' => $workload['session_count'],
                ];
            }
        }

        usort($suggestions, fn($a, $b) => $b['remaining_capacity'] <=> $a['remaining_capacity']);

        return array_slice($suggestions, 0, 5); // Top 5 suggestions
    }

    /**
     * Get slot duration in hours.
     */
    private function getSlotDuration(int $slotId): float
    {
        $slot = TimetableSlot::find($slotId);
        if (!$slot) return 1;

        $start = \Carbon\Carbon::parse($slot->start_time);
        $end = \Carbon\Carbon::parse($slot->end_time);

        return $start->diffInMinutes($end) / 60;
    }

    private function publishedTimetableScope(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function (Builder $versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn (Builder $version) => $version->where('status', 'published'));
            });
    }

    /**
     * Get teaching weeks (estimated).
     */
    private function getTeachingWeeks(int $termId): int
    {
        $term = Term::find($termId);
        if (!$term) return 16;

        $start = $term->start_date;
        $end = $term->end_date;
        $totalWeeks = $start->diffInWeeks($end);

        return max(1, $totalWeeks - 2); // Subtract 2 for exams/buffer
    }
}
