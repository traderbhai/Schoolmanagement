<?php

namespace App\Services;

use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionCommunicationSafetyService
{
    public function preview(AdmissionCommunicationTemplate $template, iterable $subjects, ?User $actor = null, array $filters = []): object
    {
        $seen = [];
        $audience = 0;
        $blocked = 0;
        $duplicates = 0;

        foreach ($subjects as $subject) {
            $audience++;
            $recipient = $this->recipient($subject, $template->channel);
            if ($recipient && isset($seen[$recipient])) {
                $duplicates++;
                continue;
            }
            $seen[$recipient] = true;
            if (! app(AdmissionConsentService::class)->allowed($subject, $template->channel) || ! $this->templateReady($template) || $this->inQuietHours($template->channel)) {
                $blocked++;
            }
        }

        $id = DB::table('admission_bulk_send_previews')->insertGetId([
            'template_id' => $template->id,
            'channel' => $template->channel,
            'filters' => json_encode($filters),
            'audience_count' => $audience,
            'blocked_count' => $blocked,
            'duplicate_count' => $duplicates,
            'status' => 'preview',
            'created_by' => $actor?->id,
            'metadata' => json_encode(['quiet_hours' => $this->inQuietHours($template->channel), 'template_approved' => $this->templateReady($template)]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('admission_bulk_send_previews')->where('id', $id)->first();
    }

    public function canSend($subject, AdmissionCommunicationTemplate $template): bool
    {
        return app(AdmissionConsentService::class)->allowed($subject, $template->channel)
            && $this->templateReady($template)
            && ! $this->inQuietHours($template->channel);
    }

    private function templateReady(AdmissionCommunicationTemplate $template): bool
    {
        return app(AdmissionTemplateApprovalService::class)->isApproved($template);
    }

    private function inQuietHours(string $channel): bool
    {
        $now = now()->format('H:i:s');
        return DB::table('admission_quiet_hour_rules')
            ->where('channel', $channel)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->where(function ($sameDay) use ($now) {
                    $sameDay->whereColumn('starts_at_time', '<', 'ends_at_time')
                        ->where('starts_at_time', '<=', $now)
                        ->where('ends_at_time', '>=', $now);
                })->orWhere(function ($overnight) use ($now) {
                    $overnight->whereColumn('starts_at_time', '>', 'ends_at_time')
                        ->where(function ($inner) use ($now) {
                            $inner->where('starts_at_time', '<=', $now)->orWhere('ends_at_time', '>=', $now);
                        });
                });
            })
            ->exists();
    }

    private function recipient($subject, string $channel): ?string
    {
        if ($subject instanceof Lead) {
            return in_array($channel, ['sms', 'whatsapp'], true) ? $subject->phone : $subject->email;
        }

        if ($subject instanceof Applicant) {
            return in_array($channel, ['sms', 'whatsapp'], true)
                ? ($subject->personal_data['phone'] ?? $subject->user?->phone ?? null)
                : ($subject->user?->email ?? $subject->personal_data['email'] ?? null);
        }

        return null;
    }
}
