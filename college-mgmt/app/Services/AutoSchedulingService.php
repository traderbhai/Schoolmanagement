<?php

namespace App\Services;

use App\Models\{AcademicPmcCourseGroup, Classroom, Batch, ProgramSubject, Teacher, TimetableSlot};
use Illuminate\Support\Collection;

class AutoSchedulingService
{
    private TimetableConflictService $conflictService;
    private TeacherWorkloadWarningService $workloadService;
    private ConflictPreventionService $preventionService;

    public function __construct(
        TimetableConflictService $conflictService,
        TeacherWorkloadWarningService $workloadService,
        ConflictPreventionService $preventionService
    ) {
        $this->conflictService = $conflictService;
        $this->workloadService = $workloadService;
        $this->preventionService = $preventionService;
    }

    /**
     * Suggest auto-schedule for a program-term-batch combination.
     */
    public function suggestSchedule(int $programId, int $termId, ?int $batchId = null): array
    {
        $courseGroups = AcademicPmcCourseGroup::with(['subject', 'batch', 'members', 'facultyAssignments.teacher.user'])
            ->where('program_id', $programId)
            ->where('term_id', $termId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->whereIn('status', ['active', 'ready', 'locked', 'rebalanced'])
            ->orderByRaw("CASE group_type WHEN 'lab_group' THEN 0 WHEN 'elective_group' THEN 1 ELSE 2 END")
            ->orderByDesc('current_strength')
            ->get();

        if ($courseGroups->isNotEmpty()) {
            return $this->suggestCourseGroupSchedule($courseGroups, $termId);
        }

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
    private function suggestCourseGroupSchedule(Collection $courseGroups, int $termId): array
    {
        $teachers = Teacher::where('status', 'active')->with(['department', 'user'])->get();
        $classrooms = Classroom::where('is_active', true)->get();
        $slots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();
        $suggestions = [];
        $unscheduled = [];
        $assignedSlots = [];

        foreach ($courseGroups as $group) {
            $subject = $group->subject;
            $batch = $group->batch;

            if (! $subject || ! $batch) {
                $unscheduled[] = $group->name;
                continue;
            }

            $teacher = $this->teacherForCourseGroup($group, $teachers, $termId);
            if (! $teacher) {
                $unscheduled[] = "{$group->name} - no available faculty";
                continue;
            }

            $bestSlot = $this->findBestSlot(
                $slots,
                $batch,
                $teacher,
                $classrooms,
                $termId,
                $assignedSlots,
                $subject,
                $group,
                $this->durationForCourseGroup($group)
            );

            if ($bestSlot) {
                $suggestions[] = [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'course_group_id' => $group->id,
                    'course_group_name' => $group->name,
                    'group_type' => $group->group_type,
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->user?->name,
                    'classroom_id' => $bestSlot['classroom_id'],
                    'classroom_name' => $bestSlot['classroom']->room_number,
                    'day_of_week' => $bestSlot['day'],
                    'timetable_slot_id' => $bestSlot['slot_id'],
                    'slot_name' => $bestSlot['slot']->name,
                    'duration_slots' => $bestSlot['duration_slots'],
                    'confidence' => round($bestSlot['confidence'], 1),
                    'reason' => $bestSlot['reason'],
                    'source' => 'canonical_pmc_course_group',
                ];

                foreach ($bestSlot['covered_slot_ids'] as $coveredSlotId) {
                    $assignedSlots[$bestSlot['day']][$coveredSlotId][] = [
                        'batch_id' => $batch->id,
                        'teacher_id' => $teacher->id,
                        'classroom_id' => $bestSlot['classroom_id'],
                        'course_group_id' => $group->id,
                        'student_ids' => $this->activeStudentIds($group),
                    ];
                }
            } else {
                $unscheduled[] = "{$subject->name} ({$group->name})";
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
    private function teacherForCourseGroup(AcademicPmcCourseGroup $group, Collection $teachers, int $termId): ?\App\Models\Teacher
    {
        $assignedTeacher = $group->facultyAssignments
            ->first(fn ($assignment) => $assignment->teacher && ! $assignment->is_backup && $assignment->teacher->status === 'active')
            ?->teacher;

        return $assignedTeacher ?: $this->findBestTeacher($teachers, $termId, $group->subject);
    }

    private function findBestSlot($slots, $batch, $teacher, $classrooms, int $termId, array $assignedSlots, $subject, ?AcademicPmcCourseGroup $courseGroup = null, int $durationSlots = 1): ?array
    {
        $best = null;
        $bestScore = -1;
        $durationSlots = max(1, $durationSlots);

        // Try each day
        for ($day = 1; $day <= 6; $day++) {
            // Try each time slot
            foreach ($slots as $slot) {
                $coveredSlotIds = $this->coveredSlotIds($slots, (int) $slot->id, $durationSlots);
                if (count($coveredSlotIds) < $durationSlots) {
                    continue;
                }

                // Check if already assigned in this search
                foreach ($coveredSlotIds as $coveredSlotId) {
                    if (isset($assignedSlots[$day][$coveredSlotId])) {
                        foreach ($assignedSlots[$day][$coveredSlotId] as $assigned) {
                            if ($this->inMemoryAssignmentConflicts($assigned, $batch->id, $teacher->id, $courseGroup)) {
                                continue 3; // Skip this start slot
                            }
                        }
                    }
                }

                // Find suitable classroom
                $classroom = $classrooms
                    ->where('capacity', '>=', $this->demandStrength($batch, $courseGroup))
                    ->first(fn (Classroom $room) => ! collect($coveredSlotIds)
                        ->contains(fn (int $coveredSlotId) => collect($assignedSlots[$day][$coveredSlotId] ?? [])->contains('classroom_id', $room->id)));
                if (!$classroom) continue;

                // Check for conflicts
                $conflicts = $courseGroup
                    ? $this->preventionService->isSlotAvailable(
                        $day,
                        $slot->id,
                        $teacher->id,
                        $classroom->id,
                        $batch->id,
                        $termId,
                        $courseGroup->id,
                        $durationSlots
                    )['conflicts']
                    : $this->conflictService->check([
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
                $score = $this->scoreAssignment($day, $slot, $teacher, $classroom, $batch, $termId, $courseGroup);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'day' => $day,
                        'slot' => $slot,
                        'slot_id' => $slot->id,
                        'classroom' => $classroom,
                        'classroom_id' => $classroom->id,
                        'duration_slots' => $durationSlots,
                        'covered_slot_ids' => $coveredSlotIds,
                        'confidence' => $bestScore,
                        'reason' => $courseGroup
                            ? 'Optimal slot for group demand with resource and student-cohort checks'
                            : 'Optimal slot with good balance of criteria',
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * Score an assignment (higher is better).
     */
    private function scoreAssignment($day, $slot, $teacher, $classroom, $batch, int $termId, ?AcademicPmcCourseGroup $courseGroup = null): float
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
        $utilization = ($this->demandStrength($batch, $courseGroup) / $classroom->capacity) * 100;
        if ($utilization >= 70 && $utilization <= 90) {
            $score += 15; // Good utilization
        }

        // Teacher workload (prefer lower workload)
        $workload = $this->workloadService->getCurrentWorkload($teacher->id, $termId);
        $workloadScore = 100 - ($workload['weekly_load'] / 18 * 100);
        $score += $workloadScore * 0.15;

        return $score;
    }

    private function inMemoryAssignmentConflicts(array $assigned, int $batchId, int $teacherId, ?AcademicPmcCourseGroup $courseGroup = null): bool
    {
        if ($assigned['teacher_id'] === $teacherId) {
            return true;
        }

        if (! $courseGroup) {
            return $assigned['batch_id'] === $batchId;
        }

        if (($assigned['course_group_id'] ?? null) === $courseGroup->id) {
            return true;
        }

        $existingStudents = collect($assigned['student_ids'] ?? []);
        $candidateStudents = $this->activeStudentIds($courseGroup);

        if ($existingStudents->isNotEmpty() || $candidateStudents->isNotEmpty()) {
            return $existingStudents->intersect($candidateStudents)->isNotEmpty();
        }

        return false;
    }

    private function activeStudentIds(AcademicPmcCourseGroup $group): Collection
    {
        return $group->members
            ->where('status', 'active')
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function durationForCourseGroup(AcademicPmcCourseGroup $group): int
    {
        $sessionMix = $group->constraints['session_mix'] ?? null;
        if (is_array($sessionMix)) {
            $durations = collect($sessionMix)
                ->map(fn ($mix) => is_array($mix) ? (int) ($mix['duration_slots'] ?? 1) : 1)
                ->filter(fn (int $duration) => $duration > 0);

            if ($durations->isNotEmpty()) {
                return max(1, (int) $durations->max());
            }
        }

        return str_contains((string) $group->group_type, 'lab') ? 2 : 1;
    }

    private function coveredSlotIds(Collection $slots, int $startSlotId, int $durationSlots): array
    {
        $ordered = $slots->sortBy('sort_order')->values();
        $startIndex = $ordered->search(fn (TimetableSlot $slot): bool => (int) $slot->id === $startSlotId);
        if ($startIndex === false) {
            return [];
        }

        return $ordered
            ->slice($startIndex, max(1, $durationSlots))
            ->reject(fn (TimetableSlot $slot): bool => (bool) $slot->is_break)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function demandStrength(Batch $batch, ?AcademicPmcCourseGroup $courseGroup = null): int
    {
        if ($courseGroup) {
            return max(1, (int) ($courseGroup->current_strength ?: $this->activeStudentIds($courseGroup)->count()));
        }

        if (array_key_exists('student_count', $batch->getAttributes()) && $batch->getAttribute('student_count') !== null) {
            return max(1, (int) $batch->getAttribute('student_count'));
        }

        return max(1, (int) ($batch->intake_capacity ?? 1));
    }
}
