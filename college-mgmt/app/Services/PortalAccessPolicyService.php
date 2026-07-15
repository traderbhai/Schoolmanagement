<?php

namespace App\Services;

class PortalAccessPolicyService
{
    public function canUseStudentPortal(?\App\Models\User $user): bool
    {
        return (bool) $user?->hasAnyRole(['student', 'admin']);
    }

    public function canUseTeacherPortal(?\App\Models\User $user): bool
    {
        return (bool) $user?->hasAnyRole(['teacher', 'faculty', 'admin']);
    }

    public function canUseParentPortal(?\App\Models\User $user): bool
    {
        return (bool) $user?->hasAnyRole(['parent', 'admin']);
    }
}
