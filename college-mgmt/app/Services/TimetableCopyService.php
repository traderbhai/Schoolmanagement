<?php

namespace App\Services;

use App\Models\{TimetableEntry, Term, Program, Batch};

class TimetableCopyService
{
    /**
     * Get available source terms (previous terms with timetable data).
     */
    public function getAvailableSourceTerms(int $programId): array
    {
        $termsWithData = TimetableEntry::where('program_id', $programId)
            ->distinct()
            ->pluck('term_id')
            ->toArray();

        if (empty($termsWithData)) {
            return [];
        }

        return Term::whereIn('id', $termsWithData)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'start_date' => $t->start_date->format('M Y'),
                'entry_count' => TimetableEntry::where('program_id', $programId)
                    ->where('term_id', $t->id)
                    ->count(),
            ])
            ->toArray();
    }

    /**
     * Preview what will be copied.
     */
    public function previewCopy(int $sourcTermId, int $targetTermId, int $programId, ?int $batchId = null): array
    {
        $sourceEntries = TimetableEntry::where('program_id', $programId)
            ->where('term_id', $sourcTermId)
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->with(['subject', 'teacher.user', 'classroom', 'batch'])
            ->get();

        // Check for conflicts in target term
        $conflicts = [];
        foreach ($sourceEntries as $entry) {
            $existingInTarget = TimetableEntry::where('program_id', $programId)
                ->where('term_id', $targetTermId)
                ->where('day_of_week', $entry->day_of_week)
                ->where('timetable_slot_id', $entry->timetable_slot_id)
                ->where('batch_id', $entry->batch_id)
                ->exists();

            if ($existingInTarget) {
                $conflicts[] = "Slot conflict: {$entry->batch->name} already has entry on " .
                    $this->getDayName($entry->day_of_week);
            }
        }

        return [
            'source_count' => $sourceEntries->count(),
            'source_batches' => $sourceEntries->pluck('batch.name')->unique()->values()->toArray(),
            'conflicts' => $conflicts,
            'can_copy' => empty($conflicts),
            'preview' => $sourceEntries->map(fn($e) => [
                'day' => $this->getDayName($e->day_of_week),
                'slot' => $e->slot->name ?? 'N/A',
                'subject' => $e->subject->name,
                'teacher' => $e->teacher?->user->name ?? 'N/A',
                'room' => $e->classroom->room_number ?? 'N/A',
                'batch' => $e->batch->name,
            ])->toArray(),
        ];
    }

    /**
     * Execute copy: create new entries in target term.
     */
    public function executeCopy(int $sourceTermId, int $targetTermId, int $programId, ?int $batchId = null, array $options = []): array
    {
        $copied = 0;
        $errors = [];

        try {
            $sourceEntries = TimetableEntry::where('program_id', $programId)
                ->where('term_id', $sourceTermId)
                ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
                ->get();

            foreach ($sourceEntries as $source) {
                try {
                    // Delete existing entry for this slot in target term (if replace mode)
                    if ($options['replace_existing'] ?? false) {
                        TimetableEntry::where('program_id', $programId)
                            ->where('term_id', $targetTermId)
                            ->where('day_of_week', $source->day_of_week)
                            ->where('timetable_slot_id', $source->timetable_slot_id)
                            ->where('batch_id', $source->batch_id)
                            ->delete();
                    }

                    // Create new entry in target term
                    $newTeacherId = $source->teacher_id;
                    $newClassroomId = $source->classroom_id;

                    // If teacher reassignment is enabled, try to map
                    if ($options['reassign_teachers'] ?? false) {
                        $newTeacherId = $this->findSubstituteTeacher($source->teacher_id);
                    }

                    // If classroom reassignment is enabled, try to map
                    if ($options['reassign_classrooms'] ?? false) {
                        $newClassroomId = $this->findSubstituteClassroom($source->classroom_id);
                    }

                    TimetableEntry::create([
                        'program_id' => $programId,
                        'term_id' => $targetTermId,
                        'batch_id' => $source->batch_id,
                        'subject_id' => $source->subject_id,
                        'teacher_id' => $newTeacherId,
                        'classroom_id' => $newClassroomId,
                        'day_of_week' => $source->day_of_week,
                        'timetable_slot_id' => $source->timetable_slot_id,
                        'is_active' => true,
                        'status' => 'draft',
                    ]);

                    $copied++;
                } catch (\Exception $e) {
                    $errors[] = "Row error: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'copied' => $copied,
                'errors' => $errors,
                'message' => "Copied {$copied} timetable entries.",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'copied' => 0,
                'errors' => [$e->getMessage()],
                'message' => 'Copy operation failed.',
            ];
        }
    }

    /**
     * Find a substitute teacher (same department, similar specialization).
     */
    private function findSubstituteTeacher(int $originalTeacherId): int
    {
        $original = \App\Models\Teacher::find($originalTeacherId);
        if (!$original) return $originalTeacherId;

        // Try to find another active teacher in same department
        $substitute = \App\Models\Teacher::where('department_id', $original->department_id)
            ->where('id', '!=', $originalTeacherId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        return $substitute?->id ?? $originalTeacherId;
    }

    /**
     * Find a substitute classroom (similar capacity, similar type).
     */
    private function findSubstituteClassroom(int $originalClassroomId): int
    {
        $original = \App\Models\Classroom::find($originalClassroomId);
        if (!$original) return $originalClassroomId;

        // Try to find another active classroom with similar capacity
        $substitute = \App\Models\Classroom::where('is_active', true)
            ->where('id', '!=', $originalClassroomId)
            ->whereRaw('ABS(capacity - ?) <= 10', [$original->capacity])
            ->orderBy('id')
            ->first();

        return $substitute?->id ?? $originalClassroomId;
    }

    private function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        return $days[$day] ?? 'Unknown';
    }
}
