<?php

namespace App\Services;

use App\Models\{TimetableEntry, TimetableSlot, Subject, Teacher, Classroom, Batch, Program, Term};
use Illuminate\Http\UploadedFile;

class TimetableImportService
{
    private TimetableConflictService $conflictService;

    public function __construct(TimetableConflictService $conflictService)
    {
        $this->conflictService = $conflictService;
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

        // Check conflicts if no errors so far
        if (empty($errors)) {
            $conflicts = $this->conflictService->check([
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
    public function importCSV(UploadedFile $file, int $programId, int $termId, ?int $batchId = null): array
    {
        $imported = 0;
        $errors = [];
        $rowNum = 0;

        try {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (empty(array_filter($row))) continue;

                $data = array_combine($header, $row);
                $actualBatchId = $batchId ?? (int)$data['batch_id'];

                try {
                    // Clear existing for this slot
                    TimetableEntry::where([
                        'program_id'        => $programId,
                        'term_id'           => $termId,
                        'batch_id'          => $actualBatchId,
                        'day_of_week'       => (int)$data['day_of_week'],
                        'timetable_slot_id' => (int)$data['timetable_slot_id'],
                    ])->delete();

                    // Create entry
                    TimetableEntry::create([
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
            ? "day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id\n"
            : "day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id,batch_id\n";

        $sample = "# Example: day_of_week (1=Mon-6=Sat), slot/subject/teacher/classroom/batch IDs\n";
        $sample .= "1,1,5,3,2" . ($batchId ? "" : ",1") . "\n";
        $sample .= "1,2,6,4,3" . ($batchId ? "" : ",1") . "\n";
        $sample .= "2,1,7,5,2" . ($batchId ? "" : ",1") . "\n";

        return $headers . $sample;
    }

    private function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        return $days[$day] ?? 'Unknown';
    }
}
