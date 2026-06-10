<?php

namespace App\Services;

use App\Models\{TimetableEntry, TimetableSlot, Subject, Teacher, Classroom, Batch, ProgramSubject, Term};

class AutoSchedulingService
{
    private TimetableConflictService $conflictService;
    private TeacherWorkloadWarningService $workloadService;

    public function __construct(
        TimetableConflictService $conflictService,
        TeacherWorkloadWarningService $workloadService
    ) {
        $this->conflictService = $conflictService;
        $this->workloadService = $workloadService;
    }

    /**
     * Suggest auto-schedule for a program-term-batch combination.
     */
    public function suggestSchedule(int $programId, int $termId, ?int $batchId = null): array
    {
        // Get subjects to schedule
        $query = ProgramSubject::where('program_id', $programId)
            ->where('term_id', $termId)
            ->with('subject');

        if ($batchId) {
            $query->whereHas('batches', fn($q) => $q->where('batch_id', $batchId));
        }

        $programSubjects = $query->get();

        if ($programSubjects->isEmpty()) {
            return ['success' => false, 'message' => 'No subjects found to schedule'];
        }

        // Get available teachers and classrooms
        $teachers = Teacher::where('status', 'active')->with('department')->get();
        $classrooms = Classroom::where('is_active', true)->get();
        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $batches = $batchId
            ? Batch::whereIn('id', [$batchId])->get()
            : Batch::where('program_id', $programId)->get();

        $suggestions = [];
        $unscheduled = [];
        $assignedSlots = []; // Track assigned slots to avoid duplicates

        foreach ($programSubjects as $progSubject) {
            $subject = $progSubject->subject;

            // Try to find best teacher
            $bestTeacher = $this->findBestTeacher($teachers, $termId, $subject);
            if (!$bestTeacher) {
                $unscheduled[] = $subject->name;
                continue;
            }

            // Try to find best slot for each batch
            foreach ($batches as $batch) {
                $bestSlot = $this->findBestSlot(
                    $slots,
                    $batch,
                    $bestTeacher,
                    $classrooms,
                    $termId,
                    $assignedSlots,
                    $subject
                );

                if ($bestSlot) {
                    $suggestions[] = [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'batch_id' => $batch->id,
                        'batch_name' => $batch->name,
                        'teacher_id' => $bestTeacher->id,
                        'teacher_name' => $bestTeacher->user->name,
                        'classroom_id' => $bestSlot['classroom_id'],
                        'classroom_name' => $bestSlot['classroom']->room_number,
                        'day_of_week' => $bestSlot['day'],
                        'timetable_slot_id' => $bestSlot['slot_id'],
                        'slot_name' => $bestSlot['slot']->name,
                        'confidence' => round($bestSlot['confidence'], 1),
                        'reason' => $bestSlot['reason'],
                    ];

                    // Track this assignment to prevent duplicates
                    $assignedSlots[$bestSlot['day']][$bestSlot['slot_id']][] = [
                        'batch_id' => $batch->id,
                        'teacher_id' => $bestTeacher->id,
                        'classroom_id' => $bestSlot['classroom_id'],
                    ];
                } else {
                    $unscheduled[] = "{$subject->name} ({$batch->name})";
                }
            }
        }

        return [
            'success' => true,
            'suggestions' => $suggestions,
            'unscheduled' => $unscheduled,
            'success_rate' => count($suggestions) > 0
                ? round((count($suggestions) / (count($suggestions) + count($unscheduled))) * 100, 1)
                : 0,
        ];
    }

    /**
     * Find best teacher for a subject (lowest workload, matching department).
     */
    private function findBestTeacher($teachers, int $termId, $subject): ?\App\Models\Teacher
    {
        $best = null;
        $minWorkload = PHP_INT_MAX;

        foreach ($teachers as $teacher) {
            // Skip if different department
            if ($subject->department_id && $teacher->department_id !== $subject->department_id) {
                continue;
            }

            $workload = $this->workloadService->getCurrentWorkload($teacher->id, $termId);
            if ($workload['weekly_load'] < $minWorkload && $workload['weekly_load'] < 18) {
                $minWorkload = $workload['weekly_load'];
                $best = $teacher;
            }
        }

        return $best;
    }

    /**
     * Find best time slot (day + time) for an assignment.
     */
    private function findBestSlot($slots, $batch, $teacher, $classrooms, int $termId, array $assignedSlots, $subject): ?array
    {
        $best = null;
        $bestScore = -1;

        // Try each day
        for ($day = 1; $day <= 6; $day++) {
            // Try each time slot
            foreach ($slots as $slot) {
                // Check if already assigned in this search
                if (isset($assignedSlots[$day][$slot->id])) {
                    foreach ($assignedSlots[$day][$slot->id] as $assigned) {
                        if ($assigned['batch_id'] === $batch->id || $assigned['teacher_id'] === $teacher->id) {
                            continue 2; // Skip this slot
                        }
                    }
                }

                // Find suitable classroom
                $classroom = $classrooms->where('capacity', '>=', $batch->student_count)->first();
                if (!$classroom) continue;

                // Check for conflicts
                $conflicts = $this->conflictService->check([
                    'teacher_id' => $teacher->id,
                    'classroom_id' => $classroom->id,
                    'batch_id' => $batch->id,
                    'day_of_week' => $day,
                    'timetable_slot_id' => $slot->id,
                    'term_id' => $termId,
                    'program_id' => 0, // Not used in this check
                ]);

                if (!empty($conflicts)) continue;

                // Score this assignment
                $score = $this->scoreAssignment($day, $slot, $teacher, $classroom, $batch, $termId);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'day' => $day,
                        'slot' => $slot,
                        'slot_id' => $slot->id,
                        'classroom' => $classroom,
                        'classroom_id' => $classroom->id,
                        'confidence' => $bestScore,
                        'reason' => 'Optimal slot with good balance of criteria',
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * Score an assignment (higher is better).
     */
    private function scoreAssignment($day, $slot, $teacher, $classroom, $batch, int $termId): float
    {
        $score = 50; // Base score

        // Preference for mid-week, mid-day slots (1-100 scale)
        $dayScore = match ($day) {
            1, 6 => 30, // Monday, Saturday - less preferred
            2, 5 => 80, // Tuesday, Friday - good
            default => 100, // Wed, Thu - most preferred
        };
        $score += $dayScore * 0.1;

        // Classroom utilization efficiency
        $utilization = ($batch->student_count / $classroom->capacity) * 100;
        if ($utilization >= 70 && $utilization <= 90) {
            $score += 15; // Good utilization
        }

        // Teacher workload (prefer lower workload)
        $workload = $this->workloadService->getCurrentWorkload($teacher->id, $termId);
        $workloadScore = 100 - ($workload['weekly_load'] / 18 * 100);
        $score += $workloadScore * 0.15;

        return $score;
    }
}
