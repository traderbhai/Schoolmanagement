<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\User;

class AcademicDeanActionVerificationService
{
    public function verify(User $actor, AcademicDeanActionItem $action, string $note = 'Verified by Dean'): AcademicDeanActionItem
    {
        $action->update(['status' => 'done', 'closure_note' => $note, 'closed_at' => now()]);
        return $action->fresh();
    }
}
