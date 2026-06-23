<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\Attendance;
use App\Models\TimetableSubstitution;
use Illuminate\Database\Eloquent\Builder;

class CanonicalSessionOperationsService
{
    public function detail(AcademicPmcTimetableGenerationItem $item): array
    {
        $item->load([
            'generationRun',
            'timetableVersion',
            'courseGroup.subject',
            'courseGroup.program',
            'courseGroup.batch',
            'courseGroup.term',
            'teacher.user',
            'classroom',
            'slot',
            'operationalTimetableEntry',
        ]);

        $memberQuery = AcademicPmcCourseGroupMember::with('student.user')
            ->where('course_group_id', $item->course_group_id)
            ->where('status', 'active');

        return [
            'item' => $item,
            'members' => $memberQuery->paginate(25, ['*'], 'members_page'),
            'member_count' => (clone $memberQuery)->count(),
            'attendance_count' => Attendance::where('pmc_generation_item_id', $item->id)
                ->orWhere(fn (Builder $query) => $item->operational_timetable_entry_id ? $query->where('timetable_entry_id', $item->operational_timetable_entry_id) : $query->whereRaw('1 = 0'))
                ->count(),
            'substitutions' => TimetableSubstitution::with(['substitute.user', 'creator'])
                ->where('pmc_generation_item_id', $item->id)
                ->orWhere(fn (Builder $query) => $item->operational_timetable_entry_id ? $query->where('timetable_entry_id', $item->operational_timetable_entry_id) : $query->whereRaw('1 = 0'))
                ->latest()
                ->paginate(10, ['*'], 'substitution_page'),
            'recommendations' => AcademicPmcSubstitutionRecommendation::with(['substituteTeacher.user', 'originalTeacher.user'])
                ->where('pmc_generation_item_id', $item->id)
                ->latest()
                ->paginate(10, ['*'], 'recommendation_page'),
            'changes' => AcademicPmcTimetableChangeRequest::with(['timetableVersion'])
                ->where('pmc_generation_item_id', $item->id)
                ->latest()
                ->paginate(10, ['*'], 'changes_page'),
            'notifications' => AcademicPmcTimetableNotification::query()
                ->where(fn (Builder $query) => $query
                    ->where('source_key', (string) $item->id)
                    ->orWhere('source_key', 'canonical:' . $item->id)
                    ->orWhere('metadata->pmc_generation_item_id', $item->id))
                ->latest()
                ->paginate(10, ['*'], 'notifications_page'),
            'bridge' => [
                'status' => $item->operational_timetable_entry_id ? 'synced' : 'missing',
                'entry_id' => $item->operational_timetable_entry_id,
            ],
            'solver' => [
                'pass' => $item->metadata['solver_pass'] ?? $item->metadata['placement_pass'] ?? null,
                'score' => $item->metadata['candidate_score'] ?? $item->metadata['placement_score'] ?? $item->confidence,
                'rejected_alternatives' => $item->metadata['rejected_alternatives'] ?? [],
                'placement_alternatives' => $item->metadata['placement_alternatives'] ?? [],
                'hard_constraints' => $item->metadata['hard_constraint_explanations'] ?? [],
                'soft_constraints' => $item->metadata['soft_constraint_explanations'] ?? $item->metadata['placement_reasons'] ?? [],
                'calendar_exceptions' => $item->metadata['calendar_exception_references'] ?? [],
            ],
        ];
    }
}
