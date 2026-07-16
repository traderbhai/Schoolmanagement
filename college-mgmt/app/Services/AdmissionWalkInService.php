<?php

namespace App\Services;

use App\Models\AdmissionWalkIn;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdmissionWalkInService
{
    public function __construct(private AdmissionAccessPolicyService $accessPolicy) {}

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

    public function report(User $viewer, array $filters = []): Collection
    {
        $reportFilters = array_intersect_key($filters, array_flip(['program_id', 'search']));

        return $this->queryFor($viewer, $reportFilters)
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

    public function queryFor(User $viewer, array $filters = []): Builder
    {
        $sort = $filters['sort'] ?? 'visited_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'visitor_name' => 'visitor_name',
            'status' => 'status',
            'visited_at' => 'visited_at',
            'next_followup_at' => 'next_followup_at',
        ];

        $query = AdmissionWalkIn::with(['program', 'counsellor', 'lead'])
            ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($scope) use ($search) {
                    $scope->where('visitor_name', 'like', "%{$search}%")
                        ->orWhere('visitor_phone', 'like', "%{$search}%")
                        ->orWhere('visitor_email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortMap[$sort] ?? 'visited_at', $direction)
            ->orderBy('id', 'desc');

        if (!$this->accessPolicy->canSeeAll($viewer)) {
            $visibleIds = $this->accessPolicy->visibleUserIds($viewer)->push($viewer->id)->unique();
            $query->where(function ($scope) use ($visibleIds) {
                $scope->whereIn('assigned_counsellor_id', $visibleIds)
                    ->orWhereNull('assigned_counsellor_id');
            });
        }

        return $query;
    }

    public function canAccess(AdmissionWalkIn $walkIn, User $viewer): bool
    {
        if ($this->accessPolicy->canSeeAll($viewer)) {
            return true;
        }

        return $this->accessPolicy->canViewAssignedUser($viewer, $walkIn->assigned_counsellor_id, true);
    }
}
