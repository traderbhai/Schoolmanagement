<?php

namespace App\Services;

use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmissionSafeCommunicationService
{
    public function __construct(
        private AdmissionCommunicationService $communication,
        private AdmissionCommunicationSafetyService $safety,
    ) {}

    public function queue(Lead|Applicant $subject, AdmissionCommunicationTemplate $template, ?User $actor = null, array $extra = [], bool $emergency = false): object
    {
        $reasons = $emergency ? [] : $this->safety->blockReasons($subject, $template);
        if ($reasons) {
            return $this->blocked($subject, $template, $actor, $reasons, $extra);
        }

        return $this->communication->queue($subject, $template, $actor, $extra + ['safety_checked' => true, 'v' => '0.039']);
    }

    public function queueCampaign(iterable $subjects, AdmissionCommunicationTemplate $template, ?User $actor = null, array $extra = []): Collection
    {
        $seen = [];

        return collect($subjects)->map(function ($subject) use ($template, $actor, $extra, &$seen) {
            $recipient = $this->recipient($subject, $template->channel);
            if ($recipient && isset($seen[$recipient])) {
                return $this->blocked($subject, $template, $actor, ['duplicate_recipient'], $extra);
            }
            $seen[$recipient ?: get_class($subject).':'.$subject->id] = true;

            return $this->queue($subject, $template, $actor, $extra);
        });
    }

    private function blocked(Lead|Applicant $subject, AdmissionCommunicationTemplate $template, ?User $actor, array $reasons, array $extra): object
    {
        $scheduledFor = in_array('quiet_hours', $reasons, true) ? now()->addHours(2) : null;
        $status = $scheduledFor ? 'delayed' : 'blocked';

        $id = DB::table('admission_blocked_communications')->insertGetId([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'template_id' => $template->id,
            'channel' => $template->channel,
            'recipient' => $this->recipient($subject, $template->channel),
            'blocked_by_rule' => $reasons[0] ?? 'safety',
            'reason' => implode(', ', $reasons),
            'actor_user_id' => $actor?->id,
            'scheduled_for' => $scheduledFor,
            'status' => $status,
            'metadata' => json_encode($extra + ['all_reasons' => $reasons, 'v' => '0.039']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('admission_blocked_communications')->where('id', $id)->first();
    }

    private function recipient(Lead|Applicant $subject, string $channel): ?string
    {
        if ($subject instanceof Lead) {
            return in_array($channel, ['sms', 'whatsapp'], true) ? $subject->phone : $subject->email;
        }

        return in_array($channel, ['sms', 'whatsapp'], true)
            ? ($subject->personal_data['phone'] ?? $subject->user?->phone ?? null)
            : ($subject->user?->email ?? $subject->personal_data['email'] ?? null);
    }
}
