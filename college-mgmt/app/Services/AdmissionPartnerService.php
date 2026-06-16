<?php

namespace App\Services;

use App\Models\AdmissionPartner;
use App\Models\Lead;
use App\Models\User;

class AdmissionPartnerService
{
    public function approve(AdmissionPartner $partner, User $actor): AdmissionPartner
    {
        $partner->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);

        return $partner->fresh();
    }

    public function submitLead(AdmissionPartner $partner, array $data): Lead
    {
        abort_unless($partner->status === 'approved', 422, 'Only approved partners can submit leads.');

        $allowedPrograms = collect($partner->allowed_program_ids ?? [])->filter()->map(fn ($id) => (int) $id);
        if ($allowedPrograms->isNotEmpty()) {
            abort_unless($allowedPrograms->contains((int) ($data['program_id'] ?? 0)), 422, 'Program is not enabled for this partner.');
        }

        return Lead::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'program_id' => $data['program_id'] ?? null,
            'source' => 'agent',
            'admission_partner_id' => $partner->id,
            'partner_reference' => $data['partner_reference'] ?? null,
            'status' => 'new',
            'priority' => $data['priority'] ?? 'normal',
            'notes' => $data['notes'] ?? null,
            'last_activity_at' => now(),
        ]);
    }

    public function dashboard(AdmissionPartner $partner): array
    {
        $leadIds = $partner->leads()->pluck('id');
        $total = $leadIds->count();
        $converted = Lead::whereIn('id', $leadIds)->where('status', 'converted')->count();

        return [
            'leads' => $total,
            'converted' => $converted,
            'conversion_pct' => $total > 0 ? round($converted / $total * 100, 1) : 0,
            'duplicates_or_rejected' => Lead::whereIn('id', $leadIds)->whereIn('status', ['not_interested'])->count(),
        ];
    }
}
