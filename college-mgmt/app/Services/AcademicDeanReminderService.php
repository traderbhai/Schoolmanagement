<?php

namespace App\Services;

use App\Models\AcademicDeanCalendarEvent;
use App\Models\User;

class AcademicDeanReminderService
{
    public function createCalendarReminder(User $actor, array $data): AcademicDeanCalendarEvent
    {
        return AcademicDeanCalendarEvent::create($data + ['event_type' => 'reminder', 'owner_user_id' => $data['owner_user_id'] ?? $actor->id, 'status' => 'scheduled']);
    }
}
