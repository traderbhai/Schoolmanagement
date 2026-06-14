<?php

namespace App\Services;

use App\Models\AcademicDeanRiskMitigation;
use App\Models\User;

class AcademicDeanRiskMitigationService
{
    public function create(User $actor, array $data): AcademicDeanRiskMitigation
    {
        return AcademicDeanRiskMitigation::create($data + ['owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
    }
}
