<?php

namespace App\Services;

use App\Models\{Classroom, Batch, TimetableEntry};

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

        $sufficient = $classroom->capacity >= $batch->student_count;
        $message = '';

        if (!$sufficient) {
            $shortage = $batch->student_count - $classroom->capacity;
            $message = "Room {$classroom->room_number} has capacity {$classroom->capacity}, but batch {$batch->name} has {$batch->student_count} students (shortage: {$shortage})";
        } else {
            $utilization = round(($batch->student_count / $classroom->capacity) * 100, 1);
            $message = "Room {$classroom->room_number} capacity: {$classroom->capacity}, utilization: {$utilization}%";
        }

        return [
            'sufficient' => $sufficient,
            'message' => $message,
            'classroom_name' => $classroom->room_number,
            'classroom_type' => $classroom->type,
            'batch_name' => $batch->name,
            'capacity' => $classroom->capacity,
            'batch_size' => $batch->student_count,
            'utilization_percent' => round(($batch->student_count / $classroom->capacity) * 100, 1),
        ];
    }

    /**
     * Get classroom utilization report for a term.
     */
    public function getUtilizationReport(int $programId, int $termId): array
    {
        $entries = TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->distinct()
            ->get(['classroom_id', 'batch_id'])
            ->groupBy('classroom_id');

        $utilization = [];

        foreach ($entries as $classroomId => $batchEntries) {
            $classroom = Classroom::find($classroomId);
            if (!$classroom) continue;

            $batches = $batchEntries->pluck('batch_id')->unique();
            $batchData = Batch::whereIn('id', $batches)->get();

            $maxBatchSize = $batchData->max('student_count');
            $totalStudents = $batchData->sum('student_count');
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

        $classrooms = Classroom::where('is_active', true)
            ->where('capacity', '>=', $batch->student_count)
            ->orderBy('capacity')
            ->get();

        return $classrooms->map(fn($room) => [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'type' => $room->type,
            'capacity' => $room->capacity,
            'utilization' => round(($batch->student_count / $room->capacity) * 100, 1),
            'available_seats' => $room->capacity - $batch->student_count,
        ])->toArray();
    }

    /**
     * Find all batches assigned to undersized rooms.
     */
    public function findCapacityViolations(int $programId, int $termId): array
    {
        $entries = TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->with(['classroom', 'batch'])
            ->get();

        $violations = [];

        foreach ($entries as $entry) {
            if ($entry->classroom->capacity < $entry->batch->student_count) {
                $violations[] = [
                    'day_of_week' => $entry->day_of_week,
                    'slot' => $entry->slot->name ?? 'N/A',
                    'subject' => $entry->subject->name ?? 'N/A',
                    'batch_name' => $entry->batch->name,
                    'batch_size' => $entry->batch->student_count,
                    'room_number' => $entry->classroom->room_number,
                    'room_capacity' => $entry->classroom->capacity,
                    'shortage' => $entry->batch->student_count - $entry->classroom->capacity,
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
