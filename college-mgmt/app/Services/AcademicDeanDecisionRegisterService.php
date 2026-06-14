<?php

namespace App\Services;

use App\Models\AcademicDeanDecision;
use App\Models\User;

class AcademicDeanDecisionRegisterService
{
    public function create(User $actor, array $data): AcademicDeanDecision
    {
        return AcademicDeanDecision::create($data + ['owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
    }
}
