<?php

namespace App\Services;

use App\Models\{AcademicPmcTimetableGenerationItem, Batch, Classroom, TimetableEntry};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClassroomCapacityService
{
    /**
     * Check if classroom capacity is sufficient for batch.
     */
    public function isCapacitySufficient(int $classroomId, int $batchId): array
    {
        $classroom = Classroom::find($classroomId);
        $batch = Batch::find($batchId);

        if (!$classroom || !$batch) {
            return [
                'sufficient' => false,
                'message' => 'Invalid classroom or batch',
                'classroom_name' => $classroom?->room_number ?? 'N/A',
                'batch_name' => $batch?->name ?? 'N/A',
                'capacity' => $classroom?->capacity ?? 0,
                'batch_size' => $batch?->student_count ?? 0,
            ];
        }

        $batchSize = $this->batchSize($batch);
        $sufficient = $classroom->capacity >= $batchSize;
        $message = '';

        if (!$sufficient) {
            $shortage = $batchSize - $classroom->capacity;
            $message = "Room {$classroom->room_number} has capacity {$classroom->capacity}, but batch {$batch->name} has {$batchSize} students (shortage: {$shortage})";
        } else {
            $utilization = round(($batchSize / $classroom->capacity) * 100, 1);
            $message = "Room {$classroom->room_number} capacity: {$classroom->capacity}, utilization: {$utilization}%";
        }

        return [
            'sufficient' => $sufficient,
            'message' => $message,
            'classroom_name' => $classroom->room_number,
            'classroom_type' => $classroom->type,
            'batch_name' => $batch->name,
            'capacity' => $classroom->capacity,
            'batch_size' => $batchSize,
            'utilization_percent' => round(($batchSize / $classroom->capacity) * 100, 1),
        ];
    }

    /**
     * Get classroom utilization report for a term.
     */
    public function getUtilizationReport(int $programId, int $termId): array
    {
        $canonicalItems = $this->officialPmcItems($programId, $termId);

        if ($canonicalItems->isNotEmpty()) {
            return $this->canonicalUtilizationReport($canonicalItems);
        }

        $entries = $this->publishedLegacyEntries($programId, $termId)
            ->distinct()
            ->get(['classroom_id', 'batch_id'])
            ->groupBy('classroom_id');

        $utilization = [];

        foreach ($entries as $classroomId => $batchEntries) {
            $classroom = Classroom::find($classroomId);
            if (!$classroom) continue;

            $batches = $batchEntries->pluck('batch_id')->unique();
            $batchData = Batch::whereIn('id', $batches)->get();

            $batchSizes = $batchData->map(fn (Batch $batch) => $this->batchSize($batch));
            $maxBatchSize = (int) $batchSizes->max();
            $totalStudents = (int) $batchSizes->sum();
            $avgBatchSize = $batchData->count() > 0 ? round($totalStudents / $batchData->count(), 1) : 0;

            $utilization[] = [
                'classroom_id' => $classroomId,
                'room_number' => $classroom->room_number,
                'room_type' => $classroom->type,
                'capacity' => $classroom->capacity,
                'batch_count' => $batchData->count(),
                'max_batch_size' => $maxBatchSize,
                'avg_batch_size' => $avgBatchSize,
                'total_students' => $totalStudents,
                'max_utilization' => round(($maxBatchSize / $classroom->capacity) * 100, 1),
                'avg_utilization' => round(($avgBatchSize / $classroom->capacity) * 100, 1),
                'status' => $this->getUtilizationStatus($classroom->capacity, $maxBatchSize),
                'batches' => $batchData->pluck('name')->toArray(),
                'has_issues' => $maxBatchSize > $classroom->capacity,
            ];
        }

        usort($utilization, fn($a, $b) => $b['max_utilization'] <=> $a['max_utilization']);

        return $utilization;
    }

    /**
     * Get rooms suitable for a batch (with sufficient capacity).
     */
    public function getSuitableClassrooms(int $batchId): array
    {
        $batch = Batch::find($batchId);
        if (!$batch) return [];
        $batchSize = $this->batchSize($batch);

        $classrooms = Classroom::where('is_active', true)
            ->where('capacity', '>=', $batchSize)
            ->orderBy('capacity')
            ->get();

        return $classrooms->map(fn($room) => [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'type' => $room->type,
            'capacity' => $room->capacity,
            'utilization' => round(($batchSize / $room->capacity) * 100, 1),
            'available_seats' => $room->capacity - $batchSize,
        ])->toArray();
    }

    /**
     * Find all batches assigned to undersized rooms.
     */
    public function findCapacityViolations(int $programId, int $termId): array
    {
        $canonicalItems = $this->officialPmcItems($programId, $termId);

        if ($canonicalItems->isNotEmpty()) {
            return $this->canonicalCapacityViolations($canonicalItems);
        }

        $entries = $this->publishedLegacyEntries($programId, $termId)
            ->with(['classroom', 'batch'])
            ->get();

        $violations = [];

        foreach ($entries as $entry) {
            $batchSize = $this->batchSize($entry->batch);
            if ($entry->classroom->capacity < $batchSize) {
                $violations[] = [
                    'day_of_week' => $entry->day_of_week,
                    'slot' => $entry->slot->name ?? 'N/A',
                    'subject' => $entry->subject->name ?? 'N/A',
                    'batch_name' => $entry->batch->name,
                    'batch_size' => $batchSize,
                    'room_number' => $entry->classroom->room_number,
                    'room_capacity' => $entry->classroom->capacity,
                    'shortage' => $batchSize - $entry->classroom->capacity,
                    'suggested_rooms' => $this->getSuitableClassrooms($entry->batch_id),
                ];
            }
        }

        usort($violations, fn($a, $b) => $b['shortage'] <=> $a['shortage']);

        return $violations;
    }

    /**
     * Determine utilization status.
     */
    private function getUtilizationStatus(int $capacity, int $maxBatchSize): string
    {
        if ($maxBatchSize > $capacity) {
            return 'over-capacity';
        }

        $utilization = ($maxBatchSize / $capacity) * 100;

        if ($utilization > 90) {
            return 'fully-utilized';
        } elseif ($utilization > 70) {
            return 'well-utilized';
        } elseif ($utilization > 40) {
            return 'moderately-utilized';
        } else {
            return 'under-utilized';
        }
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
        return AcademicPmcTimetableGenerationItem::with(['classroom', 'courseGroup.members', 'courseGroup.batch', 'courseGroup.subject', 'batch', 'slot', 'timetableVersion'])
            ->where(function (Builder $query) use ($programId, $termId) {
                $query->where(function (Builder $direct) use ($programId, $termId) {
                    $direct->where('program_id', $programId)
                        ->where('term_id', $termId);
                })->orWhereHas('courseGroup', function (Builder $group) use ($programId, $termId) {
                    $group->where('program_id', $programId)
                        ->where('term_id', $termId);
                });
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereNotNull('classroom_id')
            ->whereHas('timetableVersion', fn (Builder $query) => $query->where('status', 'published'))
            ->get();
    }

    private function publishedLegacyEntries(int $programId, int $termId): Builder
    {
        return TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where(fn (Builder $query) => $this->publishedTimetableScope($query));
    }

    private function canonicalUtilizationReport(Collection $items): array
    {
        $utilization = [];

        foreach ($items->groupBy('classroom_id') as $classroomId => $roomItems) {
            $classroom = $roomItems->first()?->classroom ?: Classroom::find($classroomId);
            if (! $classroom) {
                continue;
            }

            $groupData = $roomItems
                ->filter(fn (AcademicPmcTimetableGenerationItem $item) => $item->courseGroup)
                ->groupBy('course_group_id')
                ->map(function (Collection $groupItems) {
                    $first = $groupItems->first();
                    $group = $first->courseGroup;

                    return [
                        'name' => $group->name,
                        'size' => $this->courseGroupSize($group),
                        'batch_name' => $group->batch?->name ?? $first->batch?->name,
                    ];
                })
                ->values();

            $maxGroupSize = (int) $groupData->max('size');
            $totalStudents = (int) $groupData->sum('size');
            $avgGroupSize = $groupData->count() > 0 ? round($totalStudents / $groupData->count(), 1) : 0;

            $utilization[] = [
                'classroom_id' => (int) $classroomId,
                'room_number' => $classroom->room_number,
                'room_type' => $classroom->type,
                'capacity' => (int) $classroom->capacity,
                'batch_count' => $roomItems->count(),
                'session_count' => $roomItems->count(),
                'group_count' => $groupData->count(),
                'max_batch_size' => $maxGroupSize,
                'avg_batch_size' => $avgGroupSize,
                'total_students' => $totalStudents,
                'max_utilization' => $this->capacityPercent($maxGroupSize, (int) $classroom->capacity),
                'avg_utilization' => $this->capacityPercent($avgGroupSize, (int) $classroom->capacity),
                'status' => $this->getUtilizationStatus((int) $classroom->capacity, $maxGroupSize),
                'batches' => $groupData->pluck('name')->filter()->unique()->values()->all(),
                'has_issues' => $maxGroupSize > (int) $classroom->capacity,
                'source' => 'canonical_pmc_official_sessions',
            ];
        }

        usort($utilization, fn($a, $b) => $b['max_utilization'] <=> $a['max_utilization']);

        return $utilization;
    }

    private function canonicalCapacityViolations(Collection $items): array
    {
        $violations = [];

        foreach ($items as $item) {
            $group = $item->courseGroup;
            $classroom = $item->classroom;
            if (! $group || ! $classroom) {
                continue;
            }

            $groupSize = $this->courseGroupSize($group);
            if ((int) $classroom->capacity < $groupSize) {
                $violations[] = [
                    'day_of_week' => $item->day_of_week,
                    'slot' => $item->slot?->name ?? 'N/A',
                    'subject' => $group->subject?->name ?? 'N/A',
                    'batch_name' => $group->name,
                    'batch_size' => $groupSize,
                    'room_number' => $classroom->room_number,
                    'room_capacity' => (int) $classroom->capacity,
                    'shortage' => $groupSize - (int) $classroom->capacity,
                    'suggested_rooms' => $this->getSuitableClassrooms((int) ($group->batch_id ?: $item->batch_id)),
                    'source' => 'canonical_pmc_official_sessions',
                ];
            }
        }

        usort($violations, fn($a, $b) => $b['shortage'] <=> $a['shortage']);

        return $violations;
    }

    private function courseGroupSize($group): int
    {
        $memberCount = $group->relationLoaded('members')
            ? $group->members->where('status', 'active')->count()
            : $group->members()->where('status', 'active')->count();

        return max($memberCount, (int) ($group->current_strength ?? 0));
    }

    private function capacityPercent(float|int $value, int $capacity): float
    {
        return $capacity > 0 ? round(($value / $capacity) * 100, 1) : 0.0;
    }

    private function batchSize(Batch $batch): int
    {
        if (array_key_exists('student_count', $batch->getAttributes()) && $batch->student_count !== null) {
            return (int) $batch->student_count;
        }

        if ($batch->relationLoaded('students')) {
            $count = $batch->students->count();
            return $count > 0 ? $count : (int) ($batch->intake_capacity ?? 0);
        }

        $count = $batch->students()->count();
        return $count > 0 ? $count : (int) ($batch->intake_capacity ?? 0);
    }

    /**
     * Get summary of capacity issues.
     */
    public function getSummary(array $violations): array
    {
        return [
            'total_violations' => count($violations),
            'total_shortage_seats' => array_sum(array_column($violations, 'shortage')),
            'affected_batches' => count(array_unique(array_column($violations, 'batch_name'))),
            'critical_violations' => count(array_filter($violations, fn($v) => $v['shortage'] > 10)),
        ];
    }
}
