<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Semester;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;

class PmcTimetableBridgeSyncService
{
    public const RESPONSIBILITY = 'Compatibility bridge synchronization from canonical PMC sessions into legacy timetable entries.';

    public function markRunItemsOfficial(AcademicPmcTimetableGenerationRun $run, TimetableVersion $version, User $actor): void
    {
        AcademicPmcTimetableGenerationItem::with('courseGroup')
            ->where('generation_run_id', $run->id)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->chunkById(100, function ($items) use ($run, $version, $actor) {
                foreach ($items as $item) {
                    $group = $item->courseGroup;
                    $item->update([
                        'timetable_version_id' => $version->id,
                        'program_id' => $group?->program_id ?: $run->program_id,
                        'batch_id' => $group?->batch_id ?: $run->batch_id,
                        'term_id' => $group?->term_id ?: $run->term_id,
                        'subject_id' => $group?->subject_id,
                        'official_status' => 'published',
                        'source_type' => 'generated',
                        'published_at' => now(),
                        'published_by' => $actor->id,
                        'metadata' => array_merge($item->metadata ?: [], [
                            'canonical_official_session' => true,
                            'official_source' => 'academic_pmc_timetable_generation_items',
                            'timetable_version_id' => $version->id,
                            'published_by' => $actor->id,
                            'published_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                }
            });
    }

    public function syncRunToOperationalTimetable(AcademicPmcTimetableGenerationRun $run, TimetableVersion $version, User $actor): int
    {
        $semester = $this->operationalSemester($run->term);
        $synced = 0;

        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup.subject.program.department', 'teacher', 'classroom', 'slot'])
            ->where('generation_run_id', $run->id)
            ->where('timetable_version_id', $version->id)
            ->where('official_status', 'published')
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereNotNull('teacher_id')
            ->whereNotNull('classroom_id')
            ->whereNotNull('timetable_slot_id')
            ->get();

        foreach ($items as $item) {
            $group = $item->courseGroup;
            if (! $group || ! $group->subject || ! $item->day_of_week) {
                continue;
            }

            $course = $this->legacyCourseForGroup($group);
            $entry = $this->matchingOperationalEntry($semester, $course, $item)
                ?: new TimetableEntry();

            $entry->fill([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $group->program_id ?: $run->program_id,
                'term_id' => $group->term_id ?: $run->term_id,
                'batch_id' => $group->batch_id ?: $run->batch_id,
                'subject_id' => $group->subject_id,
                'teacher_id' => $item->teacher_id,
                'classroom_id' => $item->classroom_id,
                'timetable_slot_id' => $item->timetable_slot_id,
                'day_of_week' => $item->day_of_week,
                'is_active' => true,
                'status' => 'published',
                'timetable_version_id' => $version->id,
                'pmc_generation_item_id' => $item->id,
            ]);
            $entry->save();

            $item->update([
                'operational_timetable_entry_id' => $entry->id,
                'metadata' => array_merge($item->metadata ?: [], [
                    'operational_sync' => 'published',
                    'operational_synced_at' => now()->toDateTimeString(),
                    'operational_synced_by' => $actor->id,
                    'timetable_version_id' => $version->id,
                ]),
            ]);
            $synced++;
        }

        $this->audit($actor, 'academic_pmc_v061_operational_timetable_synced', 'PMC generated timetable synced to operational timetable entries', $version, ['generation_run_id' => $run->id, 'synced_entries' => $synced]);

        return $synced;
    }

    private function operationalSemester(?Term $term): Semester
    {
        $semester = $term
            ? Semester::where('number', $term->term_number)->first()
            : null;
        if ($semester) {
            return $semester;
        }

        $semester = Semester::current() ?: Semester::first();
        if ($semester) {
            return $semester;
        }

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

        return Semester::create([
            'academic_year_id' => $year->id,
            'name' => $term?->name ?: 'PMC Operational Term',
            'number' => $term?->term_number ?: 1,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(4)->endOfMonth()->toDateString(),
            'is_current' => true,
        ]);
    }

    private function legacyCourseForGroup(AcademicPmcCourseGroup $group): Course
    {
        $departmentId = $group->subject?->department_id
            ?: $group->program?->department_id
            ?: Department::query()->value('id');
        if (! $departmentId) {
            $departmentId = Department::firstOrCreate(['code' => 'ACAD'], ['name' => 'Academics'])->id;
        }

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

    private function matchingOperationalEntry(Semester $semester, Course $course, AcademicPmcTimetableGenerationItem $item): ?TimetableEntry
    {
        if ($item->operational_timetable_entry_id) {
            $existing = TimetableEntry::where('semester_id', $semester->id)
                ->whereKey($item->operational_timetable_entry_id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return TimetableEntry::where('semester_id', $semester->id)
            ->where('pmc_generation_item_id', $item->id)
            ->first()
            ?: TimetableEntry::where('semester_id', $semester->id)
            ->where('classroom_id', $item->classroom_id)
            ->where('timetable_slot_id', $item->timetable_slot_id)
            ->where('day_of_week', $item->day_of_week)
            ->first()
            ?: TimetableEntry::where('semester_id', $semester->id)
            ->where('teacher_id', $item->teacher_id)
            ->where('timetable_slot_id', $item->timetable_slot_id)
            ->where('day_of_week', $item->day_of_week)
            ->first();
    }

    private function audit(User $actor, string $action, string $description, mixed $subject = null, array $metadata = []): void
    {
        DepartmentActivityLog::create([
            'department_id' => Department::where('code', 'ACAD')->value('id') ?: Department::query()->value('id'),
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'metadata' => $metadata + ['version' => 'PMC OS v0.041'],
        ]);
    }
}
