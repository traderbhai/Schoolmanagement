<?php

namespace App\Services;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\User;

class PmcTimetableReadinessScopeService
{
    public const RESPONSIBILITY = 'Scoped readiness existence checks for launch and publish readiness views.';

    public function readinessChecklistScopedExists(string $check, ?User $user, callable $applyScope): bool
    {
        if (! $user) {
            return match ($check) {
                'allocations' => AcademicPmcStudentCourseAllocation::whereIn('basket_status', ['approved', 'locked', 'allocated'])->exists(),
                'groups' => AcademicPmcCourseGroup::query()->exists(),
                'faculty_assignments' => AcademicPmcGroupFacultyAssignment::query()->exists(),
                'faculty_preferences' => AcademicPmcFacultyPreference::query()->exists(),
                'locked_slots' => AcademicPmcLockedSlot::query()->exists(),
                'no_hard_conflicts' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count() === 0,
                default => false,
            };
        }

        return match ($check) {
            'allocations' => $applyScope(
                AcademicPmcStudentCourseAllocation::query(),
                $user,
                [],
                ['student' => ['program_id' => 'program', 'batch_id' => 'batch']]
            )->whereIn('basket_status', ['approved', 'locked', 'allocated'])->exists(),
            'groups' => $applyScope(
                AcademicPmcCourseGroup::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists(),
            'faculty_assignments' => $applyScope(
                AcademicPmcGroupFacultyAssignment::query(),
                $user,
                [],
                ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->exists(),
            'faculty_preferences' => $applyScope(
                AcademicPmcFacultyPreference::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists(),
            'locked_slots' => $applyScope(
                AcademicPmcLockedSlot::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists(),
            'no_hard_conflicts' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count() === 0,
            default => false,
        };
    }
}
