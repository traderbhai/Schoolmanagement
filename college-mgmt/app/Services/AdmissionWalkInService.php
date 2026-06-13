<?php

namespace App\Services;

use App\Models\AdmissionWalkIn;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionWalkInService
{
    public function record(array $data, ?User $actor = null): AdmissionWalkIn
    {
        return AdmissionWalkIn::create($data + [
            'visited_at' => $data['visited_at'] ?? now(),
            'created_by' => $actor?->id,
        ]);
    }

    public function convertToLead(AdmissionWalkIn $walkIn, ?User $actor = null): Lead
    {
        $lead = Lead::updateOrCreate(
            ['email' => $walkIn->visitor_email ?: 'walkin-' . $walkIn->id . '@demo.local'],
            [
                'name' => $walkIn->visitor_name,
                'phone' => $walkIn->visitor_phone,
                'program_id' => $walkIn->program_id,
                'source' => 'event',
                'status' => 'interested',
                'priority' => 'high',
                'assigned_to' => $walkIn->assigned_counsellor_id,
                'owner_user_id' => $walkIn->assigned_counsellor_id ?: $actor?->id,
                'current_handler_user_id' => $walkIn->assigned_counsellor_id,
                'assigned_by' => $actor?->id,
                'assignment_mode' => 'walk_in',
                'assignment_reason' => 'Walk-in campus visit conversion',
                'assigned_at' => now(),
                'last_activity_at' => now(),
                'sla_due_at' => $walkIn->next_followup_at ?: now()->addDay(),
                'next_action' => 'Follow up after walk-in visit',
                'notes' => trim(($walkIn->notes ?: '') . "\nGuardian: " . ($walkIn->guardian_name ?: 'N/A')),
            ]
        );

        $walkIn->update(['lead_id' => $lead->id, 'status' => 'converted', 'outcome' => 'converted_to_lead']);

        if ($walkIn->next_followup_at) {
            LeadFollowUp::create([
                'lead_id' => $lead->id,
                'assigned_to' => $walkIn->assigned_counsellor_id,
                'type' => 'call',
                'scheduled_at' => $walkIn->next_followup_at,
                'notes' => 'Follow-up created from walk-in visit.',
            ]);
        }

        return $lead;
    }

    public function report(array $filters = []): Collection
    {
        return AdmissionWalkIn::query()
            ->with(['program', 'counsellor', 'lead'])
            ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
            ->latest('visited_at')
            ->get()
            ->groupBy('assigned_counsellor_id')
            ->map(fn ($rows) => [
                'counsellor' => $rows->first()->counsellor?->name ?? 'Unassigned',
                'visits' => $rows->count(),
                'converted' => $rows->where('status', 'converted')->count(),
                'conversion_pct' => $rows->count() ? round($rows->where('status', 'converted')->count() / $rows->count() * 100, 1) : 0,
            ])
            ->values();
    }
}
