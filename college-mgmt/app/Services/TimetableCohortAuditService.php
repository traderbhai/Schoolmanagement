<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableGenerationItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetableCohortAuditService
{
    public function audit(array $scope = []): array
    {
        $scope = $this->cleanScope($scope);

        return [
            'scope' => $scope,
            'students_not_assigned' => $this->studentsNotAssigned($scope),
            'overlapping_group_students' => $this->overlappingGroupStudents($scope),
            'elective_clash_matrix' => $this->electiveClashMatrix($scope),
            'unrelated_parallel_electives' => $this->unrelatedParallelElectives($scope),
            'strength_mismatches' => $this->strengthMismatches($scope),
        ];
    }

    public function export(array $scope = []): StreamedResponse
    {
        $audit = $this->audit($scope);

        return response()->streamDownload(function () use ($audit) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['section', 'key', 'label', 'count', 'status']);
            foreach ($audit as $section => $rows) {
                if (! is_array($rows) || $section === 'scope') {
                    continue;
                }
                foreach ($rows['rows'] ?? [] as $row) {
                    fputcsv($out, [
                        $section,
                        $row['key'] ?? $row['student_id'] ?? $row['course_group_id'] ?? $row['pair_key'] ?? '',
                        $row['label'] ?? $row['student'] ?? $row['group'] ?? $row['pair'] ?? '',
                        $row['count'] ?? $row['shared_students'] ?? $row['active_members'] ?? '',
                        $row['status'] ?? '',
                    ]);
                }
            }
            fclose($out);
        }, 'canonical-timetable-cohort-audit.csv', ['Content-Type' => 'text/csv']);
    }

    private function studentsNotAssigned(array $scope): array
    {
        $query = AcademicPmcStudentCourseAllocation::with(['student.user', 'subject'])
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id))
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('student', fn (Builder $student) => $student->where('program_id', $id)))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('student', fn (Builder $student) => $student->where('batch_id', $id)))
            ->whereIn('basket_status', ['approved', 'allocated', 'locked'])
            ->whereDoesntHave('groupMemberships', fn (Builder $member) => $member->where('status', 'active'));

        $rows = (clone $query)->limit(50)->get()->map(fn (AcademicPmcStudentCourseAllocation $allocation) => [
            'student_id' => $allocation->student_id,
            'student' => $allocation->student?->user?->name ?? $allocation->student?->roll_number ?? 'Student #' . $allocation->student_id,
            'subject' => $allocation->subject?->name ?? $allocation->subject?->code ?? 'Subject #' . $allocation->subject_id,
            'status' => $allocation->basket_status,
        ])->values()->all();

        return $this->section((clone $query)->count(), $rows, 'Students with finalized baskets but no active timetable group.');
    }

    private function overlappingGroupStudents(array $scope): array
    {
        $rows = DB::table('academic_pmc_timetable_generation_items as items')
            ->join('academic_pmc_course_group_members as members', 'members.course_group_id', '=', 'items.course_group_id')
            ->join('students', 'students.id', '=', 'members.student_id')
            ->leftJoin('users', 'users.id', '=', 'students.user_id')
            ->where('members.status', 'active')
            ->whereIn('items.status', ['scheduled', 'published', 'locked'])
            ->whereNotNull('items.day_of_week')
            ->whereNotNull('items.timetable_slot_id')
            ->when($scope['program_id'] ?? null, fn ($query, int $id) => $query->where('items.program_id', $id))
            ->when($scope['batch_id'] ?? null, fn ($query, int $id) => $query->where('items.batch_id', $id))
            ->when($scope['term_id'] ?? null, fn ($query, int $id) => $query->where('items.term_id', $id))
            ->selectRaw('members.student_id, COALESCE(users.name, students.roll_number, students.enrollment_number) as student, items.day_of_week, items.timetable_slot_id, COUNT(DISTINCT items.course_group_id) as count')
            ->groupBy('members.student_id', 'users.name', 'students.roll_number', 'students.enrollment_number', 'items.day_of_week', 'items.timetable_slot_id')
            ->havingRaw('COUNT(DISTINCT items.course_group_id) > 1')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'student_id' => $row->student_id,
                'student' => $row->student ?: 'Student #' . $row->student_id,
                'label' => 'D' . $row->day_of_week . '/S' . $row->timetable_slot_id,
                'count' => (int) $row->count,
                'status' => 'blocked',
            ])
            ->values()
            ->all();

        return $this->section(count($rows), $rows, 'Students appearing in multiple active groups in the same scheduled slot.');
    }

    private function electiveClashMatrix(array $scope): array
    {
        $groups = $this->scopeGroupQuery($scope)
            ->whereIn('group_type', ['elective_group', 'open_elective', 'elective'])
            ->with('members')
            ->get();
        $items = AcademicPmcTimetableGenerationItem::query()
            ->whereIn('course_group_id', $groups->pluck('id'))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->get()
            ->keyBy('course_group_id');

        $rows = [];
        foreach ($groups as $leftIndex => $left) {
            foreach ($groups->slice($leftIndex + 1) as $right) {
                $shared = $left->members->where('status', 'active')->pluck('student_id')
                    ->intersect($right->members->where('status', 'active')->pluck('student_id'))
                    ->count();
                if ($shared === 0) {
                    continue;
                }

                $leftItem = $items->get($left->id);
                $rightItem = $items->get($right->id);
                $scheduledTogether = $leftItem && $rightItem
                    && (int) $leftItem->day_of_week === (int) $rightItem->day_of_week
                    && (int) $leftItem->timetable_slot_id === (int) $rightItem->timetable_slot_id;

                $rows[] = [
                    'pair_key' => $left->id . ':' . $right->id,
                    'pair' => $left->name . ' / ' . $right->name,
                    'shared_students' => $shared,
                    'status' => $scheduledTogether ? 'blocked' : 'warning',
                ];
            }
        }

        return $this->section(count($rows), array_slice($rows, 0, 50), 'Elective group pairs with shared students.');
    }

    private function unrelatedParallelElectives(array $scope): array
    {
        $items = AcademicPmcTimetableGenerationItem::with('courseGroup.members')
            ->whereHas('courseGroup', fn (Builder $query) => $this->applyGroupScope($query, $scope)->whereIn('group_type', ['elective_group', 'open_elective', 'elective']))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereNotNull('day_of_week')
            ->whereNotNull('timetable_slot_id')
            ->get()
            ->groupBy(fn (AcademicPmcTimetableGenerationItem $item) => $item->day_of_week . ':' . $item->timetable_slot_id);

        $rows = [];
        foreach ($items as $slotKey => $slotItems) {
            if ($slotItems->count() < 2) {
                continue;
            }

            $groupNames = $slotItems->pluck('courseGroup.name')->filter()->values();
            $studentSets = $slotItems->map(fn (AcademicPmcTimetableGenerationItem $item) => $item->courseGroup?->members->where('status', 'active')->pluck('student_id') ?? collect());
            $shared = $this->sharedStudentCount($studentSets);
            if ($shared === 0) {
                $rows[] = [
                    'key' => $slotKey,
                    'label' => $groupNames->implode(' / '),
                    'count' => $slotItems->count(),
                    'status' => 'ready',
                ];
            }
        }

        return $this->section(count($rows), array_slice($rows, 0, 50), 'Unrelated elective groups safely running in parallel.');
    }

    private function strengthMismatches(array $scope): array
    {
        $rows = $this->scopeGroupQuery($scope)
            ->with('members')
            ->get()
            ->filter(fn (AcademicPmcCourseGroup $group) => $group->members->where('status', 'active')->count() !== (int) $group->current_strength)
            ->map(fn (AcademicPmcCourseGroup $group) => [
                'course_group_id' => $group->id,
                'group' => $group->name,
                'active_members' => $group->members->where('status', 'active')->count(),
                'declared_strength' => (int) $group->current_strength,
                'status' => 'warning',
            ])
            ->values();

        return $this->section($rows->count(), $rows->take(50)->all(), 'Groups where active membership count differs from declared strength.');
    }

    private function section(int $count, array $rows, string $message): array
    {
        return [
            'count' => $count,
            'status' => $count > 0 ? 'warning' : 'ready',
            'message' => $message,
            'rows' => $rows,
        ];
    }

    private function sharedStudentCount(Collection $studentSets): int
    {
        $seen = collect();
        $shared = collect();
        foreach ($studentSets as $set) {
            foreach ($set as $studentId) {
                $seen->contains($studentId) ? $shared->push($studentId) : $seen->push($studentId);
            }
        }

        return $shared->unique()->count();
    }

    private function scopeGroupQuery(array $scope): Builder
    {
        return $this->applyGroupScope(AcademicPmcCourseGroup::query(), $scope);
    }

    private function applyGroupScope(Builder $query, array $scope): Builder
    {
        return $query
            ->when($scope['program_id'] ?? null, fn (Builder $query, int $id) => $query->where('program_id', $id))
            ->when($scope['batch_id'] ?? null, fn (Builder $query, int $id) => $query->where('batch_id', $id))
            ->when($scope['term_id'] ?? null, fn (Builder $query, int $id) => $query->where('term_id', $id))
            ->when($scope['subject_id'] ?? null, fn (Builder $query, int $id) => $query->where('subject_id', $id));
    }

    private function cleanScope(array $scope): array
    {
        return collect(['program_id', 'batch_id', 'term_id', 'subject_id'])
            ->mapWithKeys(fn (string $key) => [$key => filled($scope[$key] ?? null) ? (int) $scope[$key] : null])
            ->all();
    }
}
