<?php

namespace App\Services;

use App\Models\AdmissionObjectionEvent;
use App\Models\AdmissionObjectionType;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;

class AdmissionObjectionAnalyticsService
{
    public function record(Lead|Applicant $subject, AdmissionObjectionType $type, ?User $actor, ?string $notes = null): AdmissionObjectionEvent
    {
        return AdmissionObjectionEvent::create([
            'objection_type_id' => $type->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'counsellor_user_id' => $actor?->id,
            'stage' => $subject->status ?? null,
            'status' => 'open',
            'notes' => $notes,
        ]);
    }

    public function dashboard(): array
    {
        return [
            'types' => AdmissionObjectionType::withCount('events')->orderBy('category')->get(),
            'events' => AdmissionObjectionEvent::with(['type', 'subject'])->latest()->paginate(25)->withQueryString(),
            'stats' => [
                'open_objections' => AdmissionObjectionEvent::where('status', 'open')->count(),
                'resolved_objections' => AdmissionObjectionEvent::where('status', 'resolved')->count(),
                'top_category' => AdmissionObjectionType::withCount('events')->orderByDesc('events_count')->first()?->category ?? 'none',
            ],
        ];
    }
}
