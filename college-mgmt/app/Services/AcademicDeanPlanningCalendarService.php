<?php

namespace App\Services;

use App\Models\AcademicDeanCalendarEvent;

class AcademicDeanPlanningCalendarService
{
    public function dashboard(array $filters = []): array
    {
        $query = AcademicDeanCalendarEvent::query()
            ->when($filters['event_type'] ?? null, fn ($q, $v) => $q->where('event_type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['owner_user_id'] ?? null, fn ($q, $v) => $q->where('owner_user_id', $v))
            ->when($filters['program_id'] ?? null, fn ($q, $v) => $q->where('program_id', $v));

        return [
            'events' => (clone $query)->orderBy('starts_at')->paginate(30),
            'today' => (clone $query)->whereDate('starts_at', today())->count(),
            'week' => (clone $query)->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'overdue' => (clone $query)->where('starts_at', '<', now())->whereNotIn('status', ['done', 'cancelled'])->count(),
        ];
    }
}
