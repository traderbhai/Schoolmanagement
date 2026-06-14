<?php

namespace App\Services;

use App\Models\AcademicDeanOperatingRecord;
use App\Models\User;

class AcademicDeanInterventionService
{
    public function create(User $actor, array $data): AcademicDeanOperatingRecord
    {
        return AcademicDeanOperatingRecord::create($data + ['record_type' => 'student_intervention', 'owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
    }
}
