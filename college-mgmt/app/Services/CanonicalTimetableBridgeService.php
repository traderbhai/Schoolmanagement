<?php

namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\User;

class CanonicalTimetableBridgeService
{
    public function ensureBridgeForOfficialSession(AcademicPmcTimetableGenerationItem $item, ?User $actor = null): ?TimetableEntry
    {
        $item->loadMissing(['courseGroup.subject.program.department', 'courseGroup.program', 'term', 'timetableVersion']);

        if (
            $item->official_status !== 'published'
            || ! $item->timetable_version_id
            || $item->timetableVersion?->status !== 'published'
            || ! in_array($item->status, ['scheduled', 'published', 'locked'], true)
            || ! $item->teacher_id
            || ! $item->classroom_id
            || ! $item->timetable_slot_id
            || ! $item->day_of_week
        ) {
            return null;
        }

        $term = $item->term ?: $item->courseGroup?->term;
        $semester = $this->operationalSemester($term);
        $course = $this->bridgeCourse($item);

        if (! $semester || ! $course) {
            return null;
        }

        $entry = $this->matchingOperationalEntry($semester, $item) ?: new TimetableEntry();
        $group = $item->courseGroup;

        if (! $entry->exists && $this->occupiedOperationalTeacherSlot($semester, $item)) {
            return null;
        }

        $entry->fill([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $item->program_id ?: $group?->program_id,
            'term_id' => $item->term_id ?: $group?->term_id,
            'batch_id' => $item->batch_id ?: $group?->batch_id,
            'subject_id' => $item->subject_id ?: $group?->subject_id,
            'teacher_id' => $item->teacher_id,
            'classroom_id' => $item->classroom_id,
            'timetable_slot_id' => $item->timetable_slot_id,
            'day_of_week' => $item->day_of_week,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $item->timetable_version_id,
            'pmc_generation_item_id' => $item->id,
        ]);
        $entry->save();

        if ((int) $item->operational_timetable_entry_id !== (int) $entry->id) {
            $item->update([
                'operational_timetable_entry_id' => $entry->id,
                'metadata' => array_merge($item->metadata ?: [], [
                    'operational_sync' => 'attendance_bridge',
                    'operational_synced_at' => now()->toDateTimeString(),
                    'operational_synced_by' => $actor?->id,
                    'timetable_version_id' => $item->timetable_version_id,
                ]),
            ]);
        }

        return $entry;
    }

    public function ensureTeacherDayBridges(int $teacherId, int $dayOfWeek, ?Semester $semester = null, ?User $actor = null): int
    {
        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup.subject.program.department', 'courseGroup.program', 'courseGroup.term', 'term', 'timetableVersion'])
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->get()
            ->filter(fn (AcademicPmcTimetableGenerationItem $item): bool => $this->matchesSemester($item, $semester));

        $created = 0;
        foreach ($items as $item) {
            $before = $item->operational_timetable_entry_id;
            $entry = $this->ensureBridgeForOfficialSession($item, $actor);
            if ($entry && ! $before) {
                $created++;
            }
        }

        return $created;
    }

    public function ensureTeacherSemesterBridges(int $teacherId, ?Semester $semester = null, ?User $actor = null): int
    {
        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup.subject.program.department', 'courseGroup.program', 'courseGroup.term', 'term', 'timetableVersion'])
            ->where('teacher_id', $teacherId)
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->get()
            ->filter(fn (AcademicPmcTimetableGenerationItem $item): bool => $this->matchesSemester($item, $semester));

        $created = 0;
        foreach ($items as $item) {
            $before = $item->operational_timetable_entry_id;
            $entry = $this->ensureBridgeForOfficialSession($item, $actor);
            if ($entry && ! $before) {
                $created++;
            }
        }

        return $created;
    }

    public function ensureSemesterBridges(?Semester $semester = null, ?User $actor = null): int
    {
        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup.subject.program.department', 'courseGroup.program', 'courseGroup.term', 'term', 'timetableVersion'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->get()
            ->filter(fn (AcademicPmcTimetableGenerationItem $item): bool => $this->matchesSemester($item, $semester));

        $created = 0;
        foreach ($items as $item) {
            $before = $item->operational_timetable_entry_id;
            $entry = $this->ensureBridgeForOfficialSession($item, $actor);
            if ($entry && ! $before) {
                $created++;
            }
        }

        return $created;
    }

    private function matchesSemester(AcademicPmcTimetableGenerationItem $item, ?Semester $semester): bool
    {
        if (! $semester) {
            return true;
        }

        $term = $item->term ?: $item->courseGroup?->term;
        if (! $term) {
            return false;
        }

        return (int) $term->term_number === (int) $semester->number
            || (string) $term->name === (string) $semester->name;
    }

    private function operationalSemester(?Term $term): ?Semester
    {
        $semester = $term
            ? Semester::where('number', $term->term_number)->first()
            : null;

        if ($semester) {
            return $semester;
        }

        if ($term) {
            $year = AcademicYear::current() ?: AcademicYear::firstOrCreate(
                ['name' => now()->year . '-' . now()->addYear()->year],
                [
                    'start_year' => now()->year,
                    'end_year' => now()->addYear()->year,
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->addYear()->endOfYear()->toDateString(),
                    'is_current' => true,
                ]
            );
            return Semester::firstOrCreate(
                ['name' => $term->name ?: 'PMC Operational Term', 'number' => $term->term_number ?: 1],
                [
                    'academic_year_id' => $year->id,
                    'start_date' => $term->start_date ?: now()->startOfMonth()->toDateString(),
                    'end_date' => $term->end_date ?: now()->addMonths(4)->endOfMonth()->toDateString(),
                    'is_current' => false,
                ]
            );
        }

        return Semester::current() ?: Semester::first();
    }

    private function bridgeCourse(AcademicPmcTimetableGenerationItem $item): ?Course
    {
        $group = $item->courseGroup;
        $departmentId = $group?->subject?->department_id
            ?: $group?->program?->department_id
            ?: Department::query()->value('id');

        if (! $departmentId) {
            $departmentId = Department::firstOrCreate(['code' => 'ACAD'], ['name' => 'Academics'])->id;
        }

        if ($group) {
            return Course::firstOrCreate(
                ['code' => 'PMCG' . $group->id],
                [
                    'department_id' => $departmentId,
                    'name' => 'PMC Group ' . $group->name,
                    'description' => 'Operational timetable course bridge for PMC group #' . $group->id,
                    'duration_years' => 1,
                    'total_semesters' => 1,
                    'is_active' => true,
                ]
            );
        }

        return Course::firstOrCreate(
            ['code' => 'PMCS' . $item->id],
            [
                'department_id' => $departmentId,
                'name' => 'PMC Session Bridge #' . $item->id,
                'description' => 'Operational timetable bridge for PMC session #' . $item->id,
                'duration_years' => 1,
                'total_semesters' => 1,
                'is_active' => true,
            ]
        );
    }

    private function matchingOperationalEntry(Semester $semester, AcademicPmcTimetableGenerationItem $item): ?TimetableEntry
    {
        if ($item->operational_timetable_entry_id) {
            $existing = TimetableEntry::whereKey($item->operational_timetable_entry_id)
                ->where('semester_id', $semester->id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return TimetableEntry::where('semester_id', $semester->id)
            ->where('pmc_generation_item_id', $item->id)
            ->first();
    }

    private function occupiedOperationalTeacherSlot(Semester $semester, AcademicPmcTimetableGenerationItem $item): bool
    {
        return TimetableEntry::where('semester_id', $semester->id)
            ->where('teacher_id', $item->teacher_id)
            ->where('timetable_slot_id', $item->timetable_slot_id)
            ->where('day_of_week', $item->day_of_week)
            ->where(function ($query) use ($item) {
                $query->whereNull('pmc_generation_item_id')
                    ->orWhere('pmc_generation_item_id', '!=', $item->id);
            })
            ->exists();
    }
}
