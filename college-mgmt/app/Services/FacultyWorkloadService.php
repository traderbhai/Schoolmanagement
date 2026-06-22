<?php

namespace App\Services;

use App\Models\{AcademicPmcTimetableGenerationItem, TimetableEntry, TimetableSlot, Teacher, Term, Program};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FacultyWorkloadService
{
    const WORKLOAD_OVERLOAD_THRESHOLD = 18; // hours/week
    const WORKLOAD_UNDERLOAD_THRESHOLD = 6; // hours/week

    /**
     * Get workload report for all teachers in a program-term.
     */
    public function getWorkloadReport(int $programId, int $termId): array
    {
        $officialItems = $this->officialPmcItems($programId, $termId);
        if ($officialItems->isNotEmpty()) {
            return $this->canonicalWorkloadReport($officialItems, $termId);
        }

        $entries = TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where(fn (Builder $query) => $this->publishedTimetableScope($query))
            ->with(['teacher.user', 'subject', 'slot'])
            ->get();

        $workloadByTeacher = [];

        foreach ($entries as $entry) {
            $teacherId = $entry->teacher_id;
            $hours = $this->getSlotDuration($entry->timetable_slot_id);

            if (!isset($workloadByTeacher[$teacherId])) {
                $workloadByTeacher[$teacherId] = [
                    'teacher_id' => $teacherId,
                    'teacher_name' => $entry->teacher?->user->name ?? 'Unknown',
                    'total_hours' => 0,
                    'session_count' => 0,
                    'subjects' => [],
                    'entries' => [],
                ];
            }

            $workloadByTeacher[$teacherId]['total_hours'] += $hours;
            $workloadByTeacher[$teacherId]['session_count']++;
            $workloadByTeacher[$teacherId]['entries'][] = [
                'day_of_week' => $entry->day_of_week,
                'slot' => $entry->slot->name ?? 'N/A',
                'subject' => $entry->subject->name ?? 'N/A',
                'batch' => $entry->batch?->name ?? 'N/A',
                'hours' => $hours,
            ];

            // Track subjects (unique)
            $subjectKey = $entry->subject_id;
            if (!in_array($subjectKey, $workloadByTeacher[$teacherId]['subjects'])) {
                $workloadByTeacher[$teacherId]['subjects'][] = $subjectKey;
            }
        }

        // Calculate average and status
        $report = [];
        foreach ($workloadByTeacher as $teacherId => $data) {
            $weeklyLoad = $data['total_hours'] / $this->getTeachingWeeks($termId);
            $status = $this->getStatus($weeklyLoad);

            $report[] = [
                'teacher_id' => $data['teacher_id'],
                'teacher_name' => $data['teacher_name'],
                'session_count' => $data['session_count'],
                'total_hours' => $data['total_hours'],
                'teaching_weeks' => $this->getTeachingWeeks($termId),
                'weekly_load' => round($weeklyLoad, 2),
                'subject_count' => count(array_unique($data['subjects'])),
                'status' => $status,
                'status_icon' => $this->getStatusIcon($status),
                'entries' => $data['entries'],
            ];
        }

        // Sort by weekly load (descending)
        usort($report, fn($a, $b) => $b['weekly_load'] <=> $a['weekly_load']);

        return $report;
    }

    /**
     * Get summary statistics for the workload report.
     */
    public function getSummary(array $report): array
    {
        $totalTeachers = count($report);
        $overloaded = count(array_filter($report, fn($r) => $r['status'] === 'overloaded'));
        $underloaded = count(array_filter($report, fn($r) => $r['status'] === 'underloaded'));
        $optimal = count(array_filter($report, fn($r) => $r['status'] === 'optimal'));

        $avgWorkload = $totalTeachers > 0
            ? round(array_sum(array_column($report, 'weekly_load')) / $totalTeachers, 2)
            : 0;

        $maxWorkload = $totalTeachers > 0 ? max(array_column($report, 'weekly_load')) : 0;
        $minWorkload = $totalTeachers > 0 ? min(array_column($report, 'weekly_load')) : 0;

        return [
            'total_teachers' => $totalTeachers,
            'overloaded_count' => $overloaded,
            'underloaded_count' => $underloaded,
            'optimal_count' => $optimal,
            'overloaded_percent' => $totalTeachers > 0 ? round(($overloaded / $totalTeachers) * 100, 1) : 0,
            'avg_workload' => $avgWorkload,
            'max_workload' => $maxWorkload,
            'min_workload' => $minWorkload,
        ];
    }

    /**
     * Export workload report as CSV.
     */
    public function exportAsCSV(int $programId, int $termId): string
    {
        $report = $this->getWorkloadReport($programId, $termId);
        $program = Program::find($programId);
        $term = Term::find($termId);

        $csv = "Faculty Workload Report\n";
        $csv .= "Program: {$program->name}\n";
        $csv .= "Term: {$term->name}\n";
        $csv .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

        $csv .= "Teacher Name,Sessions,Total Hours,Weekly Load,Subject Count,Status\n";

        foreach ($report as $row) {
            $csv .= "{$row['teacher_name']},{$row['session_count']},{$row['total_hours']},";
            $csv .= "{$row['weekly_load']},{$row['subject_count']},{$row['status']}\n";
        }

        return $csv;
    }

    /**
     * Get duration in hours for a timetable slot.
     */
    private function getSlotDuration(int $slotId): float
    {
        $slot = TimetableSlot::find($slotId);
        if (!$slot) return 1; // Default 1 hour

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

    private function officialPmcItems(int $programId, int $termId): Collection
    {
        return AcademicPmcTimetableGenerationItem::with(['teacher.user', 'courseGroup.batch', 'courseGroup.subject', 'batch', 'subject', 'slot', 'timetableVersion'])
            ->where(function (Builder $query) use ($programId) {
                $query->where('program_id', $programId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('program_id', $programId));
            })
            ->where(function (Builder $query) use ($termId) {
                $query->where('term_id', $termId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $termId));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereNotNull('teacher_id')
            ->whereHas('timetableVersion', fn (Builder $query) => $query->where('status', 'published'))
            ->get();
    }

    private function canonicalWorkloadReport(Collection $items, int $termId): array
    {
        $workloadByTeacher = [];

        foreach ($items as $item) {
            $teacherId = $item->teacher_id;
            $hours = $this->getSlotDuration($item->timetable_slot_id) * max(1, (int) ($item->duration_slots ?? 1));
            $subject = $item->subject ?: $item->courseGroup?->subject;
            $batch = $item->batch ?: $item->courseGroup?->batch;

            if (!isset($workloadByTeacher[$teacherId])) {
                $workloadByTeacher[$teacherId] = [
                    'teacher_id' => $teacherId,
                    'teacher_name' => $item->teacher?->user->name ?? 'Unknown',
                    'total_hours' => 0,
                    'session_count' => 0,
                    'subjects' => [],
                    'entries' => [],
                ];
            }

            $workloadByTeacher[$teacherId]['total_hours'] += $hours;
            $workloadByTeacher[$teacherId]['session_count']++;
            $workloadByTeacher[$teacherId]['entries'][] = [
                'day_of_week' => $item->day_of_week,
                'slot' => $item->slot->name ?? 'N/A',
                'subject' => $subject?->name ?? 'N/A',
                'batch' => $item->courseGroup?->name ?? $batch?->name ?? 'N/A',
                'hours' => $hours,
                'source' => 'canonical_pmc_official_sessions',
            ];

            if ($subject?->id && !in_array($subject->id, $workloadByTeacher[$teacherId]['subjects'])) {
                $workloadByTeacher[$teacherId]['subjects'][] = $subject->id;
            }
        }

        $report = [];
        foreach ($workloadByTeacher as $data) {
            $weeklyLoad = $data['total_hours'] / $this->getTeachingWeeks($termId);
            $status = $this->getStatus($weeklyLoad);

            $report[] = [
                'teacher_id' => $data['teacher_id'],
                'teacher_name' => $data['teacher_name'],
                'session_count' => $data['session_count'],
                'total_hours' => $data['total_hours'],
                'teaching_weeks' => $this->getTeachingWeeks($termId),
                'weekly_load' => round($weeklyLoad, 2),
                'subject_count' => count(array_unique($data['subjects'])),
                'status' => $status,
                'status_icon' => $this->getStatusIcon($status),
                'entries' => $data['entries'],
            ];
        }

        usort($report, fn($a, $b) => $b['weekly_load'] <=> $a['weekly_load']);

        return $report;
    }

    /**
     * Get number of teaching weeks in a term (typically 18-20).
     */
    private function getTeachingWeeks(int $termId): int
    {
        $term = Term::find($termId);
        if (!$term) return 16; // Default

        $start = $term->start_date;
        $end = $term->end_date;

        // Estimate teaching weeks (exclude exam week)
        $totalWeeks = $start->diffInWeeks($end);
        return max(1, $totalWeeks - 2); // Subtract 2 weeks for exams/buffer
    }

    /**
     * Determine status based on workload.
     */
    private function getStatus(float $weeklyLoad): string
    {
        if ($weeklyLoad > self::WORKLOAD_OVERLOAD_THRESHOLD) {
            return 'overloaded';
        } elseif ($weeklyLoad < self::WORKLOAD_UNDERLOAD_THRESHOLD) {
            return 'underloaded';
        }
        return 'optimal';
    }

    /**
     * Get icon for status.
     */
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'overloaded' => '⚠️ ',
            'underloaded' => 'ℹ️ ',
            default => '✓ ',
        };
    }
}
