<?php

namespace App\Helpers;

use App\Models\{User, Student, RoleProgramAssignment};

class AccessControl
{
    public static function canManageProgram(User $user, int $programId): bool
    {
        if ($user->hasRole(['admin', 'dean_academics'])) return true;

        return RoleProgramAssignment::where('user_id', $user->id)
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->whereIn('role_name', ['program_chair', 'hod'])
            ->exists();
    }

    public static function canManageExams(User $user): bool
    {
        return $user->hasRole(['admin', 'exam_cell', 'dean_academics']);
    }

    public static function canManageFees(User $user): bool
    {
        return $user->hasRole(['admin', 'accounts_officer']);
    }

    public static function canViewStudentData(User $user, Student $student): bool
    {
        if ($user->hasRole(['admin', 'dean_academics', 'exam_cell'])) return true;

        return static::canManageProgram($user, $student->program_id);
    }
}
