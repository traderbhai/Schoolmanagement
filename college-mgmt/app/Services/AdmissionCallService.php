<?php

namespace App\Services;

use App\Models\AdmissionCallLog;
use App\Models\Applicant;
use App\Models\CounsellingLog;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionCallService
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function queueFor(User $user, array $filters = []): Collection
    {
        $query = Lead::with(['program', 'assignedTo', 'scoreRecords'])
            ->whereIn('status', ['new', 'contacted', 'interested'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('score_band')
            ->oldest('last_contacted_at');

        $this->hierarchy->applyLeadVisibility($query, $user, 'ADM');

        return $query
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->limit(100)
            ->get();
    }

    public function logCall(Lead|Applicant $subject, User $caller, array $data): AdmissionCallLog
    {
        $log = AdmissionCallLog::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'caller_user_id' => $caller->id,
            'phone' => $data['phone'] ?? ($subject instanceof Lead ? $subject->phone : ($subject->personal_data['phone'] ?? null)),
            'disposition' => $data['disposition'],
            'outcome_reason' => $data['outcome_reason'] ?? null,
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'called_at' => $data['called_at'] ?? now(),
            'next_followup_at' => $data['next_followup_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => ['source' => 'v0.03_call_queue'],
        ]);

        $updates = ['last_contacted_at' => now(), 'last_activity_at' => now(), 'next_action' => $this->nextActionFor($data)];
        if ($subject instanceof Lead && in_array($data['disposition'], ['connected', 'interested'], true)) {
            $updates['status'] = $data['disposition'] === 'interested' ? 'interested' : 'contacted';
        }
        $subject->update($updates);

        if ($subject instanceof Lead && !empty($data['next_followup_at'])) {
            LeadFollowUp::create([
                'lead_id' => $subject->id,
                'assigned_to' => $subject->assigned_to ?: $caller->id,
                'type' => 'call',
                'scheduled_at' => $data['next_followup_at'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        if ($subject instanceof Applicant) {
            CounsellingLog::create([
                'applicant_id' => $subject->id,
                'logged_by' => $caller->id,
                'interaction_type' => 'call',
                'outcome' => $data['disposition'],
                'notes' => $data['notes'] ?? null,
                'next_followup_date' => isset($data['next_followup_at']) ? \Carbon\Carbon::parse($data['next_followup_at'])->toDateString() : null,
                'duration_minutes' => max(0, (int) floor(((int) ($data['duration_seconds'] ?? 0)) / 60)),
            ]);
        }

        return $log;
    }

    public function productivityFor(User $user): array
    {
        $todayLogs = AdmissionCallLog::where('caller_user_id', $user->id)->whereDate('called_at', today());
        $completed = (clone $todayLogs)->count();
        $connected = (clone $todayLogs)->whereIn('disposition', ['connected', 'interested'])->count();

        return [
            'calls_due' => $this->queueFor($user)->count(),
            'calls_completed' => $completed,
            'contact_rate' => $completed > 0 ? round($connected / $completed * 100, 1) : 0,
            'conversion_rate' => $completed > 0 ? round((clone $todayLogs)->where('disposition', 'interested')->count() / $completed * 100, 1) : 0,
        ];
    }

    private function nextActionFor(array $data): string
    {
        return match ($data['disposition']) {
            'not_reachable' => 'Retry call',
            'call_back_later' => 'Call back',
            'interested' => 'Counsellor follow-up',
            'not_interested' => 'Review/lost reason',
            'duplicate' => 'Review duplicate',
            'converted_to_applicant' => 'Review applicant',
            'escalated_to_counsellor' => 'Counsellor action required',
            default => 'Follow up',
        };
    }
}
