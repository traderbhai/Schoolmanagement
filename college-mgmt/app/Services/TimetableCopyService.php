<?php

namespace App\Services;

use App\Models\{AcademicPmcCourseGroup, AcademicPmcTimetableGenerationItem, AcademicPmcTimetableGenerationRun, TimetableEntry, Term, Program, Batch};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetableCopyService
{
    /**
     * Get available source terms (previous terms with timetable data).
     */
    public function getAvailableSourceTerms(int $programId): array
    {
        $legacyTermIds = TimetableEntry::where('program_id', $programId)
            ->distinct()
            ->pluck('term_id')
            ->all();
        $canonicalTermIds = AcademicPmcTimetableGenerationItem::with('courseGroup:id,program_id,term_id')
            ->where(function (Builder $query) use ($programId) {
                $query->where('program_id', $programId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('program_id', $programId));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn (Builder $query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->get(['id', 'program_id', 'term_id', 'course_group_id'])
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->canonicalTermId($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $termsWithData = array_values(array_unique(array_merge($legacyTermIds, $canonicalTermIds)));

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
                'entry_count' => $this->canonicalItems((int) $t->id, $programId)->count()
                    ?: TimetableEntry::where('program_id', $programId)->where('term_id', $t->id)->count(),
            ])
            ->toArray();
    }

    /**
     * Preview what will be copied.
     */
    public function previewCopy(int $sourcTermId, int $targetTermId, int $programId, ?int $batchId = null): array
    {
        $canonicalItems = $this->canonicalItems($sourcTermId, $programId, $batchId);
        if ($canonicalItems->isNotEmpty()) {
            return $this->previewCanonicalCopy($canonicalItems, $targetTermId, $programId);
        }

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
            $canonicalItems = $this->canonicalItems($sourceTermId, $programId, $batchId);
            if ($canonicalItems->isNotEmpty()) {
                return $this->executeCanonicalCopy($canonicalItems, $targetTermId, $programId, $batchId, $options);
            }

            $sourceEntries = TimetableEntry::where('program_id', $programId)
                ->where('term_id', $sourceTermId)
                ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
                ->get();

            foreach ($sourceEntries as $source) {
                try {
                    // Delete existing entry for this slot in target term (if replace mode)
                    if ($options['replace_existing'] ?? false) {
                        $replaceError = $this->replaceDraftSlot(
                            $programId,
                            $targetTermId,
                            (int) $source->batch_id,
                            (int) $source->day_of_week,
                            (int) $source->timetable_slot_id
                        );

                        if ($replaceError !== null) {
                            $errors[] = "Skipped {$source->batch?->name} {$this->getDayName((int) $source->day_of_week)}: {$replaceError}";
                            continue;
                        }
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
                        'semester_id' => $options['semester_id'] ?? $source->semester_id,
                        'course_id' => $options['course_id'] ?? $source->course_id,
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

    private function replaceDraftSlot(int $programId, int $termId, int $batchId, int $dayOfWeek, int $slotId): ?string
    {
        $existing = TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where('batch_id', $batchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->withCount(['attendances', 'substitutions'])
            ->get();

        $locked = $existing->first(fn (TimetableEntry $entry): bool =>
            $entry->status !== 'draft'
            || $entry->timetable_version_id !== null
            || $entry->attendances_count > 0
            || $entry->substitutions_count > 0
        );

        if ($locked) {
            return 'existing timetable history is locked; use the PMC revision/version workflow';
        }

        $existing->each->delete();

        return null;
    }

    private function canonicalItems(int $termId, int $programId, ?int $batchId = null): Collection
    {
        return AcademicPmcTimetableGenerationItem::with(['courseGroup.batch', 'subject', 'teacher.user', 'classroom', 'batch', 'slot'])
            ->where(function (Builder $query) use ($termId, $programId) {
                $query->where(function (Builder $direct) use ($termId, $programId) {
                    $direct->where('program_id', $programId)
                        ->where('term_id', $termId);
                })->orWhereHas('courseGroup', function (Builder $group) use ($termId, $programId) {
                    $group->where('program_id', $programId)
                        ->where('term_id', $termId);
                });
            })
            ->when($batchId, fn (Builder $query) => $query->where(function (Builder $scope) use ($batchId) {
                $scope->where('batch_id', $batchId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('batch_id', $batchId));
            }))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn (Builder $query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->get();
    }

    private function previewCanonicalCopy(Collection $sourceItems, int $targetTermId, int $programId): array
    {
        $conflicts = [];

        foreach ($sourceItems as $item) {
            $targetGroup = $this->matchingTargetCourseGroup($item, $targetTermId);
            if (! $targetGroup) {
                $conflicts[] = 'Missing target group for ' . ($item->courseGroup?->name ?? $item->subject?->name ?? 'session');
                continue;
            }

            $locked = $this->lockedCanonicalTarget($targetGroup->id, (int) $item->day_of_week, (int) $item->timetable_slot_id);
            if ($locked) {
                $conflicts[] = 'Target group slot is locked for ' . $targetGroup->name . ' on ' . $this->getDayName((int) $item->day_of_week);
            }
        }

        return [
            'source_count' => $sourceItems->count(),
            'source_batches' => $sourceItems
                ->map(fn (AcademicPmcTimetableGenerationItem $item) => $item->courseGroup?->batch?->name ?? $item->batch?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'conflicts' => $conflicts,
            'can_copy' => empty($conflicts),
            'preview' => $sourceItems->map(fn (AcademicPmcTimetableGenerationItem $item) => [
                'day' => $this->getDayName((int) $item->day_of_week),
                'slot' => $item->slot?->name ?? 'N/A',
                'subject' => $item->subject?->name ?? $item->courseGroup?->subject?->name ?? 'N/A',
                'teacher' => $item->teacher?->user?->name ?? 'N/A',
                'room' => $item->classroom?->room_number ?? 'N/A',
                'batch' => $item->courseGroup?->batch?->name ?? $item->batch?->name ?? 'N/A',
                'course_group' => $item->courseGroup?->name,
                'source' => 'canonical_pmc_session',
            ])->all(),
        ];
    }

    private function executeCanonicalCopy(Collection $sourceItems, int $targetTermId, int $programId, ?int $batchId, array $options): array
    {
        $copied = 0;
        $errors = [];

        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Copied PMC timetable sessions',
            'strategy' => 'copy_from_previous_term',
            'program_id' => $programId,
            'batch_id' => $batchId,
            'term_id' => $targetTermId,
            'created_by' => $options['created_by'] ?? null,
            'status' => 'draft',
            'scheduled_count' => 0,
            'unscheduled_count' => 0,
            'quality_score' => 0,
            'input_summary' => [
                'source_term_id' => $this->canonicalTermId($sourceItems->first()),
                'source' => 'canonical_pmc_generation_items',
            ],
        ]);

        foreach ($sourceItems as $index => $source) {
            try {
                $targetGroup = $this->matchingTargetCourseGroup($source, $targetTermId);
                if (! $targetGroup) {
                    $errors[] = 'Missing target group for ' . ($source->courseGroup?->name ?? $source->subject?->name ?? 'session');
                    continue;
                }

                if ($options['replace_existing'] ?? false) {
                    $replaceError = $this->replaceDraftCanonicalGroupSlot($targetGroup->id, (int) $source->day_of_week, (int) $source->timetable_slot_id);
                    if ($replaceError !== null) {
                        $errors[] = "Skipped {$targetGroup->name} {$this->getDayName((int) $source->day_of_week)}: {$replaceError}";
                        continue;
                    }
                } elseif ($this->hasCanonicalTargetSession($targetGroup->id, (int) $source->day_of_week, (int) $source->timetable_slot_id)) {
                    $errors[] = "Skipped {$targetGroup->name} {$this->getDayName((int) $source->day_of_week)}: target slot already has a canonical session";
                    continue;
                }

                AcademicPmcTimetableGenerationItem::create([
                    'generation_run_id' => $run->id,
                    'course_group_id' => $targetGroup->id,
                    'program_id' => $programId,
                    'batch_id' => $targetGroup->batch_id,
                    'term_id' => $targetTermId,
                    'subject_id' => $targetGroup->subject_id ?? $source->subject_id,
                    'session_index' => $index + 1,
                    'session_type' => $source->session_type ?? 'lecture',
                    'duration_slots' => max(1, (int) ($source->duration_slots ?? 1)),
                    'teacher_id' => $source->teacher_id,
                    'classroom_id' => $source->classroom_id,
                    'day_of_week' => $source->day_of_week,
                    'timetable_slot_id' => $source->timetable_slot_id,
                    'status' => 'scheduled',
                    'official_status' => 'draft',
                    'source_type' => 'copy_from_previous_term',
                    'metadata' => [
                        'source_item_id' => $source->id,
                        'source_term_id' => $this->canonicalTermId($source),
                        'source_course_group_id' => $source->course_group_id,
                    ],
                ]);

                $copied++;
            } catch (\Exception $e) {
                $errors[] = 'Row error: ' . $e->getMessage();
            }
        }

        $run->update(['scheduled_count' => $copied]);

        return [
            'success' => true,
            'copied' => $copied,
            'errors' => $errors,
            'message' => "Copied {$copied} canonical PMC timetable sessions.",
        ];
    }

    private function matchingTargetCourseGroup(AcademicPmcTimetableGenerationItem $source, int $targetTermId): ?AcademicPmcCourseGroup
    {
        $sourceGroup = $source->courseGroup;
        if (! $sourceGroup) {
            return null;
        }

        return AcademicPmcCourseGroup::where('program_id', $sourceGroup->program_id)
            ->where('batch_id', $sourceGroup->batch_id)
            ->where('term_id', $targetTermId)
            ->where('subject_id', $sourceGroup->subject_id)
            ->where('group_type', $sourceGroup->group_type)
            ->where('name', $sourceGroup->name)
            ->first();
    }

    private function canonicalTermId(?AcademicPmcTimetableGenerationItem $item): ?int
    {
        if (! $item) {
            return null;
        }

        return $item->term_id ?? $item->courseGroup?->term_id;
    }

    private function hasCanonicalTargetSession(int $courseGroupId, int $dayOfWeek, int $slotId): bool
    {
        return AcademicPmcTimetableGenerationItem::where('course_group_id', $courseGroupId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->exists();
    }

    private function lockedCanonicalTarget(int $courseGroupId, int $dayOfWeek, int $slotId): bool
    {
        return AcademicPmcTimetableGenerationItem::where('course_group_id', $courseGroupId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->where(fn (Builder $query) => $query
                ->where('official_status', 'published')
                ->orWhereNotNull('timetable_version_id')
                ->orWhere('is_locked', true))
            ->exists();
    }

    private function replaceDraftCanonicalGroupSlot(int $courseGroupId, int $dayOfWeek, int $slotId): ?string
    {
        $existing = AcademicPmcTimetableGenerationItem::where('course_group_id', $courseGroupId)
            ->where('day_of_week', $dayOfWeek)
            ->where('timetable_slot_id', $slotId)
            ->get();

        $locked = $existing->first(fn (AcademicPmcTimetableGenerationItem $item): bool =>
            $item->official_status === 'published'
            || $item->timetable_version_id !== null
            || $item->is_locked
        );

        if ($locked) {
            return 'existing canonical timetable history is locked; use the PMC revision/version workflow';
        }

        $existing->each->delete();

        return null;
    }
}
