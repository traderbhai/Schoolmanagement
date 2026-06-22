<?php

namespace App\Services;

use App\Models\{AcademicPmcTimetableGenerationItem, TimetableEntry, TeacherAvailability};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SoftConstraintService
{
    /**
     * Audit soft constraints for a term and return recommendations.
     */
    public function auditTermConstraints(int $termId, int $programId, ?int $batchId = null): array
    {
        $canonicalItems = $this->canonicalItems($termId, $programId, $batchId);
        $entries = $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->displayEntryFromPmcItem($item))
            ->toBase();
        $canonicalProgramTermBatchKeys = $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->programTermBatchKeyForPmcItem($item))
            ->filter()
            ->unique()
            ->values();

        $legacyEntries = TimetableEntry::where('term_id', $termId)
            ->where('program_id', $programId)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->where('is_active', true)
            ->with(['subject', 'batch', 'teacher.user', 'slot'])
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->get()
            ->reject(fn (TimetableEntry $entry) => $canonicalProgramTermBatchKeys->contains($this->programTermBatchKey($entry->program_id, $entry->term_id, $entry->batch_id)))
            ->map(fn (TimetableEntry $entry) => $this->displayEntryFromLegacyEntry($entry))
            ->toBase();

        $entries = $entries->merge($legacyEntries)->values();

        $issues = [];

        // 1. Check subject spread (each subject should be spread across week, not all on same day)
        $issues = array_merge($issues, $this->checkSubjectSpread($entries));

        // 2. Check lab sessions grouping (labs should be back-to-back if possible)
        $issues = array_merge($issues, $this->checkLabGrouping($entries));

        // 3. Check teacher availability preferences
        $issues = array_merge($issues, $this->checkTeacherAvailability($entries, $termId));

        // 4. Check break time violations
        $issues = array_merge($issues, $this->checkBreakTimes($entries));

        // 5. Check teacher gap analysis (long gaps between classes)
        $issues = array_merge($issues, $this->checkTeacherGaps($entries));

        return [
            'total_issues' => count($issues),
            'issues' => $issues,
            'summary' => $this->getSummary($issues),
        ];
    }

    /**
     * Check if subjects are spread across the week (not all on one day).
     */
    private function checkSubjectSpread(iterable $entries): array
    {
        $issues = [];

        // Group by batch and subject
        $subjectDays = [];
        foreach ($entries as $entry) {
            $key = ($entry->course_group_id ?? $entry->batch_id) . '-' . $entry->subject_id;
            if (!isset($subjectDays[$key])) {
                $subjectDays[$key] = [
                    'subject' => $entry->subject->name ?? 'Unknown',
                    'batch' => $entry->course_group?->name ?? $entry->batch->name ?? 'Unknown',
                    'days' => [],
                    'count' => 0,
                ];
            }
            $subjectDays[$key]['days'][] = $entry->day_of_week;
            $subjectDays[$key]['count']++;
        }

        // Check for subjects with all classes on same day
        foreach ($subjectDays as $entry) {
            $uniqueDays = count(array_unique($entry['days']));
            if ($entry['count'] >= 3 && $uniqueDays <= 1) {
                $issues[] = [
                    'type' => 'subject_not_spread',
                    'severity' => 'warning',
                    'subject' => $entry['subject'],
                    'batch' => $entry['batch'],
                    'message' => "Subject '{$entry['subject']}' for {$entry['batch']} has {$entry['count']} classes all on same day. Consider spreading across week.",
                    'recommendation' => 'Spread classes across 2-3 different days for better retention.',
                ];
            }
        }

        return $issues;
    }

    /**
     * Check if lab sessions are grouped together.
     */
    private function checkLabGrouping(iterable $entries): array
    {
        $issues = [];
        $labEntries = collect($entries)->filter(function ($e) {
            return ($e->session_type ?? null) === 'lab'
                || strpos(strtolower($e->subject->name ?? ''), 'lab') !== false;
        });

        // Group labs by subject and day
        $labsBySubjectDay = [];
        foreach ($labEntries as $entry) {
            $key = ($entry->course_group_id ?? $entry->subject_id) . '-' . $entry->day_of_week;
            if (!isset($labsBySubjectDay[$key])) {
                $labsBySubjectDay[$key] = [];
            }
            foreach ($this->coveredSlotSortOrders($entry) as $slotOrder) {
                $labsBySubjectDay[$key][] = $slotOrder;
            }
        }

        // Check if labs are consecutive
        foreach ($labsBySubjectDay as $key => $slots) {
            if (count($slots) >= 2) {
                sort($slots);
                $consecutive = true;
                for ($i = 1; $i < count($slots); $i++) {
                    if ($slots[$i] != $slots[$i - 1] + 1) {
                        $consecutive = false;
                        break;
                    }
                }

                if (!$consecutive) {
                    $issues[] = [
                        'type' => 'lab_not_grouped',
                        'severity' => 'info',
                        'subject' => "Lab with {$key}",
                        'message' => 'Lab sessions for this subject are not back-to-back on the same day. Consider grouping them together.',
                        'recommendation' => 'Group lab sessions in consecutive time slots for better workflow.',
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Check if assignments violate teacher availability preferences.
     */
    private function checkTeacherAvailability(iterable $entries, int $termId): array
    {
        $issues = [];
        $availabilities = TeacherAvailability::where('term_id', $termId)->get();

        foreach ($entries as $entry) {
            $availability = $availabilities->firstWhere(function ($a) use ($entry) {
                return $a->teacher_id === $entry->teacher_id
                    && $a->day_of_week === $entry->day_of_week
                    && $a->timetable_slot_id === $entry->timetable_slot_id;
            });

            if ($availability && $availability->availability === 'unavailable') {
                $issues[] = [
                    'type' => 'unavailable_time_assigned',
                    'severity' => 'warning',
                    'teacher' => $entry->teacher->user->name ?? 'Unknown',
                    'subject' => $entry->subject->name ?? 'Unknown',
                    'day' => $this->getDayName($entry->day_of_week),
                    'slot' => $entry->slot->name ?? 'N/A',
                    'message' => "Teacher {$entry->teacher->user->name} marked as unavailable at this time but has a class assigned.",
                    'recommendation' => 'Update teacher availability or reassign class to different time.',
                ];
            }
        }

        return $issues;
    }

    /**
     * Check if any assignments violate break times.
     */
    private function checkBreakTimes(iterable $entries): array
    {
        $issues = [];
        $breaks = \App\Models\TimetableSlot::where('is_break', true)->pluck('id')->toArray();

        foreach ($entries as $entry) {
            if (in_array($entry->timetable_slot_id, $breaks)) {
                $issues[] = [
                    'type' => 'class_during_break',
                    'severity' => 'error',
                    'subject' => $entry->subject->name ?? 'Unknown',
                    'batch' => $entry->batch->name ?? 'Unknown',
                    'day' => $this->getDayName($entry->day_of_week),
                    'slot' => $entry->slot->name ?? 'N/A',
                    'message' => "Class scheduled during break time ({$entry->slot->name}).",
                    'recommendation' => 'Reassign class to non-break time slot.',
                ];
            }
        }

        return $issues;
    }

    /**
     * Check for large gaps in teacher schedule (more than 2 hours between classes).
     */
    private function checkTeacherGaps(iterable $entries): array
    {
        $issues = [];

        // Group by teacher and day
        $teacherDays = [];
        foreach ($entries as $entry) {
            $key = $entry->teacher_id . '-' . $entry->day_of_week;
            if (!isset($teacherDays[$key])) {
                $teacherDays[$key] = [
                    'teacher' => $entry->teacher->user->name ?? 'Unknown',
                    'day' => $this->getDayName($entry->day_of_week),
                    'slots' => [],
                ];
            }
            foreach ($this->coveredSlotSortOrders($entry) as $slotOrder) {
                $teacherDays[$key]['slots'][] = $slotOrder;
            }
        }

        // Check for gaps
        foreach ($teacherDays as $dayEntries) {
            $slots = $dayEntries['slots'];
            if (count($slots) >= 2) {
                sort($slots);
                for ($i = 1; $i < count($slots); $i++) {
                    $gap = $slots[$i] - $slots[$i - 1];
                    if ($gap > 2) {
                        $issues[] = [
                            'type' => 'teacher_large_gap',
                            'severity' => 'info',
                            'teacher' => $dayEntries['teacher'],
                            'day' => $dayEntries['day'],
                            'message' => "{$dayEntries['teacher']} has a large gap ({$gap} slots) on {$dayEntries['day']}.",
                            'recommendation' => 'Consider rearranging to reduce idle time between classes.',
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Get constraint issue summary.
     */
    private function getSummary(array $issues): array
    {
        return [
            'total_issues' => count($issues),
            'errors' => count(array_filter($issues, fn($i) => $i['severity'] === 'error')),
            'warnings' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
            'info' => count(array_filter($issues, fn($i) => $i['severity'] === 'info')),
        ];
    }

    private function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        return $days[$day] ?? 'Unknown';
    }

    private function canonicalItems(int $termId, int $programId, ?int $batchId = null): Collection
    {
        return AcademicPmcTimetableGenerationItem::with([
            'subject',
            'courseGroup.subject',
            'courseGroup.batch',
            'teacher.user',
            'batch',
            'slot',
        ])
            ->where(function (Builder $query) use ($termId, $programId) {
                $query->where(function (Builder $direct) use ($termId, $programId) {
                    $direct->where('term_id', $termId)
                        ->where('program_id', $programId);
                })->orWhereHas('courseGroup', function (Builder $group) use ($termId, $programId) {
                    $group->where('term_id', $termId)
                        ->where('program_id', $programId);
                });
            })
            ->when($batchId, function (Builder $query) use ($batchId) {
                $query->where(function (Builder $scope) use ($batchId) {
                    $scope->where('batch_id', $batchId)
                        ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('batch_id', $batchId));
                });
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn (Builder $query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->get();
    }

    private function displayEntryFromPmcItem(AcademicPmcTimetableGenerationItem $item): object
    {
        return (object) [
            'source' => 'canonical_pmc_session',
            'program_id' => $item->program_id ?? $item->courseGroup?->program_id,
            'term_id' => $item->term_id ?? $item->courseGroup?->term_id,
            'batch_id' => $item->batch_id ?? $item->courseGroup?->batch_id,
            'course_group_id' => $item->course_group_id,
            'subject_id' => $item->subject_id ?? $item->courseGroup?->subject_id,
            'subject' => $item->subject ?? $item->courseGroup?->subject,
            'batch' => $item->batch ?? $item->courseGroup?->batch,
            'course_group' => $item->courseGroup,
            'teacher_id' => $item->teacher_id,
            'teacher' => $item->teacher,
            'slot' => $item->slot,
            'day_of_week' => $item->day_of_week,
            'timetable_slot_id' => $item->timetable_slot_id,
            'session_type' => $item->session_type,
            'duration_slots' => max(1, (int) ($item->duration_slots ?? 1)),
        ];
    }

    private function displayEntryFromLegacyEntry(TimetableEntry $entry): object
    {
        return (object) [
            'source' => 'legacy_timetable_entry',
            'program_id' => $entry->program_id,
            'term_id' => $entry->term_id,
            'batch_id' => $entry->batch_id,
            'course_group_id' => null,
            'subject_id' => $entry->subject_id,
            'subject' => $entry->subject,
            'batch' => $entry->batch,
            'course_group' => null,
            'teacher_id' => $entry->teacher_id,
            'teacher' => $entry->teacher,
            'slot' => $entry->slot,
            'day_of_week' => $entry->day_of_week,
            'timetable_slot_id' => $entry->timetable_slot_id,
            'session_type' => null,
            'duration_slots' => 1,
        ];
    }

    private function coveredSlotSortOrders(object $entry): array
    {
        $start = $entry->slot?->sort_order ?? $entry->timetable_slot_id;
        $duration = max(1, (int) ($entry->duration_slots ?? 1));

        return range((int) $start, (int) $start + $duration - 1);
    }

    private function programTermBatchKey(?int $programId, ?int $termId, ?int $batchId): ?string
    {
        if (! $programId || ! $termId) {
            return null;
        }

        return $programId . ':' . $termId . ':' . ($batchId ?? 'none');
    }

    private function programTermBatchKeyForPmcItem(AcademicPmcTimetableGenerationItem $item): ?string
    {
        return $this->programTermBatchKey(
            $item->program_id ?? $item->courseGroup?->program_id,
            $item->term_id ?? $item->courseGroup?->term_id,
            $item->batch_id ?? $item->courseGroup?->batch_id,
        );
    }
}
