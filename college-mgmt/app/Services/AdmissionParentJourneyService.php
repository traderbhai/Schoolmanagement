<?php

namespace App\Services;

use App\Models\AdmissionParentJourney;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;

class AdmissionParentJourneyService
{
    public function ensure(Lead|Applicant $subject, ?User $owner = null): AdmissionParentJourney
    {
        $data = $subject instanceof Lead ? [] : ($subject->personal_data ?? []);

        return AdmissionParentJourney::firstOrCreate(
            ['subject_type' => get_class($subject), 'subject_id' => $subject->id],
            [
                'guardian_name' => $data['guardian_name'] ?? data_get($subject, 'guardian_name'),
                'guardian_phone' => $data['guardian_phone'] ?? data_get($subject, 'phone'),
                'guardian_email' => $data['guardian_email'] ?? data_get($subject, 'email'),
                'preferred_channel' => 'phone',
                'decision_status' => 'contact_pending',
                'next_action' => 'Call parent/guardian and confirm decision maker',
                'next_due_at' => now()->addDay(),
                'owner_user_id' => $owner?->id,
                'metadata' => ['v' => '0.037'],
            ]
        );
    }

    public function createReminder(AdmissionParentJourney $journey, ?User $actor = null): AdmissionReminderSchedule
    {
        return AdmissionReminderSchedule::create([
            'subject_type' => $journey->subject_type,
            'subject_id' => $journey->subject_id,
            'owner_user_id' => $journey->owner_user_id ?? $actor?->id,
            'assigned_to' => $journey->owner_user_id ?? $actor?->id,
            'reason' => 'parent_guardian_followup',
            'channel' => $journey->preferred_channel === 'whatsapp' ? 'whatsapp' : 'email',
            'status' => 'scheduled',
            'priority' => 'high',
            'due_at' => $journey->next_due_at ?? now()->addDay(),
            'notes' => $journey->next_action,
            'metadata' => ['journey_id' => $journey->id],
        ]);
    }

    public function dashboard(): array
    {
        return [
            'journeys' => AdmissionParentJourney::with('owner')->orderBy('next_due_at')->paginate(25)->withQueryString(),
            'stats' => [
                'due_parent_calls' => AdmissionParentJourney::where('next_due_at', '<=', now()->addDay())->count(),
                'pending_decisions' => AdmissionParentJourney::where('decision_status', '!=', 'confirmed')->count(),
            ],
        ];
    }
}
