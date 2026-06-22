<?php

namespace App\Services;

use App\Models\{AcademicPmcCourseGroup, AcademicPmcTimetableGenerationItem, AcademicPmcTimetableGenerationRun, TimetableEntry, TimetableSlot, Subject, Teacher, Classroom, Batch, Program, Term};
use Illuminate\Http\UploadedFile;

class TimetableImportService
{
    private TimetableConflictService $conflictService;
    private ConflictPreventionService $preventionService;

    public function __construct(TimetableConflictService $conflictService, ConflictPreventionService $preventionService)
    {
        $this->conflictService = $conflictService;
        $this->preventionService = $preventionService;
    }

    /**
     * Validate CSV file structure and data.
     * Returns ['valid' => bool, 'errors' => array, 'preview' => array]
     */
    public function validateCSV(UploadedFile $file, int $programId, int $termId, ?int $batchId = null): array
    {
        $errors = [];
        $preview = [];
        $rowNum = 0;

        try {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);

            // Expected columns
            $expectedColumns = ['day_of_week', 'timetable_slot_id', 'subject_id', 'teacher_id', 'classroom_id'];
            if ($batchId === null) {
                $expectedColumns[] = 'batch_id';
            }

            // Validate header
            foreach ($expectedColumns as $col) {
                if (!in_array($col, $header ?? [])) {
                    $errors[] = "Missing required column: {$col}";
                }
            }

            if (!empty($errors)) {
                fclose($handle);
                return ['valid' => false, 'errors' => $errors, 'preview' => []];
            }

            // Validate rows
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (empty(array_filter($row))) continue; // Skip empty rows

                $data = array_combine($header, $row);
                $rowErrors = $this->validateRow($data, $programId, $termId, $batchId);

                if (!empty($rowErrors)) {
                    $errors[] = "Row {$rowNum}: " . implode('; ', $rowErrors);
                } else {
                    $preview[] = [
                        'row' => $rowNum,
                        'day' => $this->getDayName((int)$data['day_of_week']),
                        'slot' => TimetableSlot::find((int)$data['timetable_slot_id'])->name ?? 'N/A',
                        'subject' => Subject::find((int)$data['subject_id'])->name ?? 'N/A',
                        'teacher' => Teacher::find((int)$data['teacher_id'])?->user->name ?? 'N/A',
                        'classroom' => Classroom::find((int)$data['classroom_id'])->room_number ?? 'N/A',
                        'batch' => $batchId ? Batch::find($batchId)->name : Batch::find((int)$data['batch_id'])?->name,
                        'course_group' => ! empty($data['course_group_id']) ? AcademicPmcCourseGroup::find((int) $data['course_group_id'])?->name : null,
                    ];
                }
            }

            fclose($handle);

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'preview' => $preview,
                'total_rows' => $rowNum,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => ['File error: ' . $e->getMessage()],
                'preview' => [],
            ];
        }
    }

    /**
     * Validate a single row of data.
     */
    private function validateRow(array $row, int $programId, int $termId, ?int $batchId = null): array
    {
        $errors = [];

        // Required fields
        if (empty($row['day_of_week']) || !is_numeric($row['day_of_week']) || $row['day_of_week'] < 1 || $row['day_of_week'] > 6) {
            $errors[] = 'day_of_week must be 1-6';
        }

        if (empty($row['timetable_slot_id']) || !TimetableSlot::find((int)$row['timetable_slot_id'])) {
            $errors[] = 'Invalid timetable_slot_id';
        }

        if (empty($row['subject_id']) || !Subject::find((int)$row['subject_id'])) {
            $errors[] = 'Invalid subject_id';
        }

        if (empty($row['teacher_id']) || !Teacher::find((int)$row['teacher_id'])) {
            $errors[] = 'Invalid teacher_id';
        }

        if (empty($row['classroom_id']) || !Classroom::find((int)$row['classroom_id'])) {
            $errors[] = 'Invalid classroom_id';
        }

        $actualBatchId = $batchId ?? ($row['batch_id'] ?? null);
        if (empty($actualBatchId) || !Batch::find((int)$actualBatchId)) {
            $errors[] = 'Invalid batch_id';
        }

        $courseGroup = null;
        if (! empty($row['course_group_id'])) {
            $courseGroup = AcademicPmcCourseGroup::find((int) $row['course_group_id']);
            if (! $courseGroup) {
                $errors[] = 'Invalid course_group_id';
            } elseif (
                (int) $courseGroup->program_id !== (int) $programId
                || (int) $courseGroup->term_id !== (int) $termId
                || (int) $courseGroup->batch_id !== (int) $actualBatchId
                || (int) $courseGroup->subject_id !== (int) $row['subject_id']
            ) {
                $errors[] = 'course_group_id does not match program, term, batch, and subject';
            }
        }

        // Check conflicts if no errors so far
        if (empty($errors)) {
            $conflicts = $courseGroup
                ? $this->preventionService->isSlotAvailable(
                    (int) $row['day_of_week'],
                    (int) $row['timetable_slot_id'],
                    (int) $row['teacher_id'],
                    (int) $row['classroom_id'],
                    (int) $actualBatchId,
                    $termId,
                    $courseGroup->id
                )['conflicts']
                : $this->conflictService->check([
                    'teacher_id'        => (int)$row['teacher_id'],
                    'classroom_id'      => (int)$row['classroom_id'],
                    'batch_id'          => (int)$actualBatchId,
                    'day_of_week'       => (int)$row['day_of_week'],
                    'timetable_slot_id' => (int)$row['timetable_slot_id'],
                    'term_id'           => $termId,
                ]);

            if (!empty($conflicts)) {
                $errors[] = 'Conflict: ' . implode('; ', array_slice($conflicts, 0, 2));
            }
        }

        return $errors;
    }

    /**
     * Import CSV rows into database.
     * Returns ['success' => bool, 'imported' => int, 'errors' => array]
     */
    public function importCSV(UploadedFile $file, int $programId, int $termId, ?int $batchId = null, array $options = []): array
    {
        $imported = 0;
        $errors = [];
        $rowNum = 0;

        try {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);
            $canonicalRun = null;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (empty(array_filter($row))) continue;

                $data = array_combine($header, $row);
                $actualBatchId = $batchId ?? (int)$data['batch_id'];

                try {
                    if (! empty($data['course_group_id'])) {
                        $courseGroup = AcademicPmcCourseGroup::find((int) $data['course_group_id']);
                        if (
                            ! $courseGroup
                            || (int) $courseGroup->program_id !== (int) $programId
                            || (int) $courseGroup->term_id !== (int) $termId
                            || (int) $courseGroup->batch_id !== (int) $actualBatchId
                            || (int) $courseGroup->subject_id !== (int) $data['subject_id']
                        ) {
                            $errors[] = "Row {$rowNum}: course_group_id does not match program, term, batch, and subject";
                            continue;
                        }

                        $replaceError = $this->replaceDraftCanonicalGroupSlot(
                            (int) $data['course_group_id'],
                            (int) $data['day_of_week'],
                            (int) $data['timetable_slot_id']
                        );

                        if ($replaceError !== null) {
                            $errors[] = "Row {$rowNum}: {$replaceError}";
                            continue;
                        }

                        $canonicalRun ??= AcademicPmcTimetableGenerationRun::create([
                            'title' => 'Imported PMC timetable CSV',
                            'strategy' => 'csv_import',
                            'program_id' => $programId,
                            'batch_id' => $batchId,
                            'term_id' => $termId,
                            'created_by' => $options['created_by'] ?? null,
                            'status' => 'draft',
                            'scheduled_count' => 0,
                            'unscheduled_count' => 0,
                            'quality_score' => 0,
                            'input_summary' => [
                                'source' => 'program_chair_csv_import',
                            ],
                        ]);

                        AcademicPmcTimetableGenerationItem::create([
                            'generation_run_id' => $canonicalRun->id,
                            'course_group_id' => (int) $data['course_group_id'],
                            'program_id' => $programId,
                            'batch_id' => $actualBatchId,
                            'term_id' => $termId,
                            'subject_id' => (int) $data['subject_id'],
                            'session_index' => $imported + 1,
                            'session_type' => $data['session_type'] ?? $this->sessionTypeForCourseGroup($courseGroup),
                            'duration_slots' => isset($data['duration_slots']) && is_numeric($data['duration_slots'])
                                ? max(1, (int) $data['duration_slots'])
                                : $this->durationForCourseGroup($courseGroup),
                            'teacher_id' => (int) $data['teacher_id'],
                            'classroom_id' => (int) $data['classroom_id'],
                            'day_of_week' => (int) $data['day_of_week'],
                            'timetable_slot_id' => (int) $data['timetable_slot_id'],
                            'status' => 'scheduled',
                            'official_status' => 'draft',
                            'source_type' => 'csv_import',
                            'metadata' => [
                                'row' => $rowNum,
                            ],
                        ]);

                        $imported++;
                        continue;
                    }

                    $replaceError = $this->replaceDraftSlot(
                        $programId,
                        $termId,
                        (int) $actualBatchId,
                        (int) $data['day_of_week'],
                        (int) $data['timetable_slot_id']
                    );

                    if ($replaceError !== null) {
                        $errors[] = "Row {$rowNum}: {$replaceError}";
                        continue;
                    }

                    // Create entry
                    TimetableEntry::create([
                        'semester_id'       => $options['semester_id'] ?? null,
                        'course_id'         => $options['course_id'] ?? null,
                        'program_id'        => $programId,
                        'term_id'           => $termId,
                        'batch_id'          => $actualBatchId,
                        'subject_id'        => (int)$data['subject_id'],
                        'teacher_id'        => (int)$data['teacher_id'],
                        'classroom_id'      => (int)$data['classroom_id'],
                        'day_of_week'       => (int)$data['day_of_week'],
                        'timetable_slot_id' => (int)$data['timetable_slot_id'],
                        'is_active'         => true,
                        'status'            => 'draft',
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }

            fclose($handle);

            $canonicalRun?->update(['scheduled_count' => AcademicPmcTimetableGenerationItem::where('generation_run_id', $canonicalRun->id)->count()]);

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'imported' => 0,
                'errors' => ['File error: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Get sample CSV content.
     */
    public function getSampleCSV(?int $batchId = null): string
    {
        $headers = $batchId
            ? "day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id,course_group_id,session_type,duration_slots\n"
            : "day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id,batch_id,course_group_id,session_type,duration_slots\n";

        $sample = "# Example: day_of_week (1=Mon-6=Sat), slot/subject/teacher/classroom/batch IDs\n";
        $sample .= "1,1,5,3,2" . ($batchId ? "" : ",1") . ",,lecture,1\n";
        $sample .= "1,2,6,4,3" . ($batchId ? "" : ",1") . ",12,lab,2\n";
        $sample .= "2,1,7,5,2" . ($batchId ? "" : ",1") . ",,lecture,1\n";

        return $headers . $sample;
    }

    private function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        return $days[$day] ?? 'Unknown';
    }

    private function replaceDraftSlot(int $programId, int $termId, int $batchId, int $dayOfWeek, int $slotId): ?string
    {
        $existing = TimetableEntry::where([
            'program_id' => $programId,
            'term_id' => $termId,
            'batch_id' => $batchId,
            'day_of_week' => $dayOfWeek,
            'timetable_slot_id' => $slotId,
        ])->withCount(['attendances', 'substitutions'])->get();

        $locked = $existing->first(fn (TimetableEntry $entry): bool =>
            $entry->status !== 'draft'
            || $entry->timetable_version_id !== null
            || $entry->attendances_count > 0
            || $entry->substitutions_count > 0
        );

        if ($locked) {
            return 'Existing timetable history for this slot is locked. Use the PMC revision/version workflow instead of import replacement.';
        }

        $existing->each->delete();

        return null;
    }

    private function replaceDraftCanonicalGroupSlot(int $courseGroupId, int $dayOfWeek, int $slotId): ?string
    {
        $existing = AcademicPmcTimetableGenerationItem::where([
            'course_group_id' => $courseGroupId,
            'day_of_week' => $dayOfWeek,
            'timetable_slot_id' => $slotId,
        ])->get();

        $locked = $existing->first(fn (AcademicPmcTimetableGenerationItem $item): bool =>
            $item->official_status === 'published'
            || $item->timetable_version_id !== null
            || $item->is_locked
        );

        if ($locked) {
            return 'Existing canonical timetable history for this group slot is locked. Use the PMC revision/version workflow instead of import replacement.';
        }

        $existing->each->delete();

        return null;
    }

    private function sessionTypeForCourseGroup(?AcademicPmcCourseGroup $courseGroup): string
    {
        return $courseGroup?->group_type === 'lab_group' ? 'lab' : 'lecture';
    }

    private function durationForCourseGroup(?AcademicPmcCourseGroup $courseGroup): int
    {
        return $courseGroup?->group_type === 'lab_group' ? 2 : 1;
    }
}
