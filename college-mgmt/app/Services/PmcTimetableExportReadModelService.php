<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PmcTimetableExportReadModelService
{
    public const RESPONSIBILITY = 'PMC timetable source-surface filtering, sorting, and CSV export row read models.';

    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function filter(Builder $query, array $filters): Builder
    {
        $table = $query->getModel()->getTable();

        foreach (['program_id', 'batch_id', 'term_id', 'subject_id', 'student_id', 'allocation_type'] as $field) {
            if (! empty($filters[$field]) && Schema::hasColumn($table, $field)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['status'])) {
            if (Schema::hasColumn($table, 'status')) {
                $query->where('status', $filters['status']);
            } elseif ($query->getModel() instanceof AcademicPmcStudentCourseAllocation) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('basket_status', $filters['status'])
                    ->orWhere('approval_status', $filters['status']));
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            if ($query->getModel() instanceof AcademicPmcStudentCourseAllocation) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('student.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('student', fn (Builder $student) => $student
                            ->where('enrollment_number', 'like', "%{$search}%")
                            ->orWhere('roll_number', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            } elseif ($query->getModel() instanceof AcademicPmcCourseGroup) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('group_type', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            }
        }

        return $query;
    }

    public function exportRows(User $user, string $surface, array $filters): array
    {
        return match ($surface) {
            'course-allocation', 'elective-allocation', 'student-course-baskets' => $this->courseAllocationExportRows($user, $filters),
            'sections', 'course-groups', 'group-memberships' => $this->courseGroupExportRows($user, $filters),
            'timetable-planner' => $this->timetablePlannerExportRows($user, $filters),
            default => [['surface', 'message'], collect([[$surface, 'Export is not configured for this v0.041 surface yet.']])],
        };
    }

    public function applyTimetableItemSort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'day_of_week';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, ['day_of_week', 'timetable_slot_id', 'status', 'confidence'], true)) {
            $query->orderBy($sort, $direction)->orderBy('timetable_slot_id');
            return;
        }

        $query->orderBy('day_of_week')->orderBy('timetable_slot_id');
    }

    private function applyScope(Builder $query, User $user, array $directMap = [], array $relationMap = []): Builder
    {
        if ($this->policy->canIgnorePmcScope($user)) {
            return $query;
        }

        $scopes = [
            'program' => $this->policy->scopedProgramIds($user),
            'batch' => $this->policy->scopedBatchIds($user),
            'term' => $this->policy->scopedTermIds($user),
            'subject' => $this->policy->scopedSubjectIds($user),
        ];

        foreach ($directMap as $column => $scopeType) {
            if (! array_key_exists($scopeType, $scopes)) {
                continue;
            }
            $ids = $scopes[$scopeType];
            if ($ids === null) {
                continue;
            }
            if (! is_array($ids) || empty($ids)) {
                return $query->whereRaw('1 = 0');
            }
            $query->whereIn($column, $ids);
        }

        foreach ($relationMap as $relation => $mapping) {
            $query->whereHas($relation, function (Builder $relatedQuery) use ($mapping, $scopes): void {
                foreach ($mapping as $column => $scopeType) {
                    if (! array_key_exists($scopeType, $scopes)) {
                        continue;
                    }
                    $ids = $scopes[$scopeType];
                    if ($ids === null) {
                        continue;
                    }
                    if (! is_array($ids) || empty($ids)) {
                        $relatedQuery->whereRaw('1 = 0');
                        continue;
                    }
                    $relatedQuery->whereIn($column, $ids);
                }
            });
        }

        return $query;
    }

    private function courseAllocationExportRows(User $user, array $filters): array
    {
        $query = $this->applyScope(
            $this->filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters),
            $user,
            [],
            ['term' => ['id' => 'term'], 'student' => ['program_id' => 'program', 'batch_id' => 'batch']]
        )->latest();

        return [
            ['student', 'subject', 'type', 'approval', 'basket', 'waitlisted', 'term'],
            $query->limit(1000)->get()->map(fn ($row) => [
                $row->student?->user?->name ?? $row->student?->enrollment_number ?? $row->student?->roll_number ?? $row->student?->student_id ?? '',
                $row->subject?->name ?? $row->subject?->code ?? '',
                $row->allocation_type,
                $row->approval_status,
                $row->basket_status,
                $row->waitlisted ? 'yes' : 'no',
                $row->term?->name ?? '',
            ]),
        ];
    }

    private function courseGroupExportRows(User $user, array $filters): array
    {
        $query = $this->applyScope(
            $this->filter(AcademicPmcCourseGroup::with(['program', 'subject', 'owner']), $filters),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();

        return [
            ['group', 'type', 'program', 'subject', 'strength', 'status', 'locked'],
            $query->limit(1000)->get()->map(fn ($row) => [
                $row->name,
                $row->group_type,
                $row->program?->code ?? '',
                $row->subject?->name ?? $row->subject?->code ?? '',
                $row->current_strength . '/' . $row->max_capacity,
                $row->status,
                $row->is_locked ? 'yes' : 'no',
            ]),
        ];
    }

    private function timetablePlannerExportRows(User $user, array $filters): array
    {
        $query = $this->applyScope(
            AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot']),
            $user,
            [],
            ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'], 'courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )
            ->when($filters['status'] ?? null, fn (Builder $item, string $status) => $item->where('status', $status), fn (Builder $item) => $item->where('status', 'scheduled'))
            ->when($filters['subject_id'] ?? null, fn (Builder $item, string $subjectId) => $item->whereHas('courseGroup', fn (Builder $group) => $group->where('subject_id', $subjectId)))
            ->when($filters['search'] ?? null, function (Builder $item, string $search) {
                $item->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('courseGroup', fn (Builder $group) => $group->where('name', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                        ->orWhereHas('teacher.user', fn (Builder $teacher) => $teacher->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('classroom', fn (Builder $room) => $room->where('name', 'like', "%{$search}%")->orWhere('room_number', 'like', "%{$search}%"));
                });
            });

        $this->applyTimetableItemSort($query, $filters);

        return [
            ['day', 'slot', 'group', 'subject', 'faculty', 'room', 'status', 'locked', 'confidence'],
            $query->limit(1000)->get()->map(fn ($row) => [
                $row->day_of_week,
                $row->slot?->name ?? $row->timetable_slot_id,
                $row->courseGroup?->name ?? '',
                $row->courseGroup?->subject?->name ?? '',
                $row->teacher?->user?->name ?? '',
                $row->classroom?->name ?? $row->classroom?->room_number ?? '',
                $row->status,
                $row->is_locked ? 'yes' : 'no',
                $row->confidence,
            ]),
        ];
    }
}
