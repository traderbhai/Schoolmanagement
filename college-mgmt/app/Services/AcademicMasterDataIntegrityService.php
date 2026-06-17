<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicMasterDataIntegrityService
{
    public function dependencyLabels(string $entity, int $id): array
    {
        return collect(match ($entity) {
            'program' => [
                'students' => ['students', 'program_id'],
                'batches' => ['batches', 'program_id'],
                'subjects' => ['subjects', 'program_id'],
                'terms' => ['terms', 'program_id'],
                'exams' => ['exams', 'program_id'],
                'timetable entries' => ['timetable_entries', 'program_id'],
                'student grievances' => ['student_grievances', 'program_id'],
                'fee structures' => ['fee_structures', 'program_id'],
                'applicants' => ['applicants', 'program_id'],
                'program subjects' => ['program_subjects', 'program_id'],
                'PMC course groups' => ['academic_pmc_course_groups', 'program_id'],
                'PMC allocation batches' => ['academic_pmc_course_allocation_batches', 'program_id'],
                'PMC generation runs' => ['academic_pmc_timetable_generation_runs', 'program_id'],
            ],
            'batch' => [
                'students' => ['students', 'batch_id'],
                'applicants' => ['applicants', 'batch_id'],
                'offer letters' => ['offer_letters', 'batch_id'],
                'application windows' => ['application_windows', 'batch_id'],
                'enrollment confirmations' => ['enrollment_confirmations', 'batch_id'],
                'PMC course groups' => ['academic_pmc_course_groups', 'batch_id'],
                'PMC allocation batches' => ['academic_pmc_course_allocation_batches', 'batch_id'],
                'PMC generation runs' => ['academic_pmc_timetable_generation_runs', 'batch_id'],
            ],
            'term' => [
                'legacy enrollments' => ['enrollments', 'term_id'],
                'student subject enrollments' => ['student_subject_enrollments', 'term_id'],
                'exams' => ['exams', 'term_id'],
                'timetable entries' => ['timetable_entries', 'term_id'],
                'fee demands' => ['fee_demands', 'term_id'],
                'academic calendar events' => ['academic_calendars', 'term_id'],
                'term promotions from this term' => ['term_promotions', 'current_term_id'],
                'term promotions to this term' => ['term_promotions', 'promoted_to_term_id'],
                'PMC student allocations' => ['academic_pmc_student_course_allocations', 'term_id'],
                'PMC course groups' => ['academic_pmc_course_groups', 'term_id'],
                'PMC generation runs' => ['academic_pmc_timetable_generation_runs', 'term_id'],
                'PMC locked slots' => ['academic_pmc_locked_slots', 'term_id'],
                'faculty preferences' => ['academic_pmc_faculty_preferences', 'term_id'],
            ],
            'subject' => [
                'legacy enrollments' => ['enrollments', 'subject_id'],
                'student subject enrollments' => ['student_subject_enrollments', 'subject_id'],
                'exams' => ['exams', 'subject_id'],
                'timetable entries' => ['timetable_entries', 'subject_id'],
                'program subjects' => ['program_subjects', 'subject_id'],
                'course outcomes' => ['course_outcomes', 'subject_id'],
                'study materials' => ['study_materials', 'subject_id'],
                'assignments' => ['assignments', 'subject_id'],
                'quizzes' => ['quizzes', 'subject_id'],
                'PMC student allocations' => ['academic_pmc_student_course_allocations', 'subject_id'],
                'PMC course groups' => ['academic_pmc_course_groups', 'subject_id'],
                'PMC elective choices' => ['academic_pmc_elective_choices', 'subject_id'],
                'PMC delivery checkpoints' => ['academic_pmc_course_delivery_checkpoints', 'subject_id'],
            ],
            'department' => [
                'programs' => ['programs', 'department_id'],
                'courses' => ['courses', 'department_id'],
                'subjects' => ['subjects', 'department_id'],
                'teachers' => ['teachers', 'department_id'],
                'students' => ['students', 'department_id'],
                'department roles' => ['department_roles', 'department_id'],
                'department teams' => ['department_teams', 'department_id'],
                'department members' => ['department_members', 'department_id'],
            ],
            default => [],
        })->filter(fn (array $dependency) => $this->exists($dependency[0], $dependency[1], $id))
            ->keys()
            ->values()
            ->all();
    }

    public function hasDependencies(string $entity, int $id): bool
    {
        return $this->dependencyLabels($entity, $id) !== [];
    }

    public function message(string $entity, array $dependencies): string
    {
        return 'Cannot delete this ' . $entity . ' because it is linked to ' . implode(', ', $dependencies) . '. Deactivate it instead to preserve academic history.';
    }

    private function exists(string $table, string $column, int $id): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)->where($column, $id)->exists();
    }
}
