<?php

namespace App\Services;

use App\Models\User;

class PortalAccessPolicyService
{
    public function canUseStudentPortal(User $user): bool
    {
        return $user->hasAnyRole(['student', 'admin']);
    }

    public function canUseTeacherPortal(User $user): bool
    {
        return $user->hasAnyRole(['teacher', 'faculty', 'admin']);
    }

    public function canUseParentPortal(User $user): bool
    {
        return $user->hasAnyRole(['parent', 'admin']);
    }
}

