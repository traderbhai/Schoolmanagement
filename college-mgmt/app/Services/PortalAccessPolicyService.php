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

    public function authorizeStudentPortal(?\App\Models\User $user): void
    {
        abort_unless($this->canUseStudentPortal($user), 403);
    }

    public function authorizeTeacherPortal(?\App\Models\User $user): void
    {
        abort_unless($this->canUseTeacherPortal($user), 403);
    }

    public function authorizeParentPortal(?\App\Models\User $user): void
    {
        abort_unless($this->canUseParentPortal($user), 403);
    }
}
