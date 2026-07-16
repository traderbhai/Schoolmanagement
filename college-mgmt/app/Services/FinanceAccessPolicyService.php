<?php

namespace App\Services;

use App\Models\User;

class FinanceAccessPolicyService
{
    public function canViewFinance(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'accounts_officer']);
    }

    public function canManageFinance(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'accounts_officer']);
    }

    public function canManageFeeOperations(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accounts_officer']);
    }

    public function authorizeView(User $user): void
    {
        abort_unless($this->canViewFinance($user), 403);
    }

    public function authorizeManage(User $user): void
    {
        abort_unless($this->canManageFinance($user), 403);
    }

    public function authorizeFeeOperations(User $user): void
    {
        abort_unless($this->canManageFeeOperations($user), 403);
    }
}
