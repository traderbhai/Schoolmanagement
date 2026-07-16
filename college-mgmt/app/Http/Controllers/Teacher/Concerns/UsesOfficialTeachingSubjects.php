<?php

namespace App\Http\Controllers\Teacher\Concerns;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Builder;

trait UsesOfficialTeachingSubjects
{
    private function officialTeachingSubjectIds(?Teacher $teacher = null): array
    {
        $teacher ??= auth()->user()?->teacher;
        if (! $teacher) {
            return [];
        }

        $canonicalItems = $this->officialPmcTeachingItems($teacher)->get();
        if ($canonicalItems->isNotEmpty()) {
            return $canonicalItems
                ->map(fn (AcademicPmcTimetableGenerationItem $item) => $item->subject_id ?? $item->courseGroup?->subject_id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $this->legacyPublishedTeachingEntries($teacher)->pluck('subject_id')->filter()->unique()->values()->all();
    }

    private function teachesOfficialSubject(int $subjectId, ?int $programId = null, ?int $termId = null, ?int $semesterId = null, ?Teacher $teacher = null): bool
    {
        $teacher ??= auth()->user()?->teacher;
        if (! $teacher) {
            return false;
        }

        $canonicalExists = $this->officialPmcTeachingItems($teacher)
            ->where(function (Builder $query) use ($subjectId) {
                $query->where('subject_id', $subjectId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('subject_id', $subjectId));
            })
            ->when($programId, function (Builder $query) use ($programId) {
                $query->where(function (Builder $scope) use ($programId) {
                    $scope->where('program_id', $programId)
                        ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('program_id', $programId));
                });
            })
            ->when($termId, function (Builder $query) use ($termId) {
                $query->where(function (Builder $scope) use ($termId) {
                    $scope->where('term_id', $termId)
                        ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('term_id', $termId));
                });
            })
            ->when($semesterId && ! $termId, function (Builder $query) use ($semesterId) {
                $semesterNumber = Semester::whereKey($semesterId)->value('number');
                if ($semesterNumber) {
                    $query->whereHas('term', fn (Builder $term) => $term->where('term_number', $semesterNumber));
                }
            })
            ->exists();

        if ($canonicalExists) {
            return true;
        }

        if ($this->officialPmcTeachingItems($teacher)->exists()) {
            return false;
        }

        return $this->legacyPublishedTeachingEntries($teacher)
            ->where('subject_id', $subjectId)
            ->when($programId, fn (Builder $query) => $query->where('program_id', $programId))
            ->when($termId || $semesterId, function (Builder $query) use ($termId, $semesterId) {
                $query->where(function (Builder $scope) use ($termId, $semesterId) {
                    if ($termId) {
                        $scope->orWhere('term_id', $termId);
                    }
                    if ($semesterId) {
                        $scope->orWhere('semester_id', $semesterId);
                    }
                });
            })
            ->exists();
    }

    private function officialTeachingTermIdForSubject(int $subjectId, ?Teacher $teacher = null): ?int
    {
        $teacher ??= auth()->user()?->teacher;
        if (! $teacher) {
            return null;
        }

        $canonicalTermId = $this->officialPmcTeachingItems($teacher)
            ->where(function (Builder $query) use ($subjectId) {
                $query->where('subject_id', $subjectId)
                    ->orWhereHas('courseGroup', fn (Builder $group) => $group->where('subject_id', $subjectId));
            })
            ->orderByDesc('published_at')
            ->value('term_id');

        if ($canonicalTermId) {
            return (int) $canonicalTermId;
        }

        return $this->legacyPublishedTeachingEntries($teacher)
            ->where('subject_id', $subjectId)
            ->orderByDesc('id')
            ->value('term_id');
    }

    private function officialPmcTeachingItems(Teacher $teacher): Builder
    {
        return AcademicPmcTimetableGenerationItem::with(['courseGroup'])
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'));
    }

    private function legacyPublishedTeachingEntries(Teacher $teacher): Builder
    {
        return TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function (Builder $query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn (Builder $version) => $version->where('status', 'published'));
            });
    }
}
