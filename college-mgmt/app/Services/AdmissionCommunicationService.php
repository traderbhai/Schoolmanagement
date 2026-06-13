<?php

namespace App\Services;

use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdmissionCommunicationService
{
    public function render(AdmissionCommunicationTemplate $template, Lead|Applicant $subject, array $extra = []): array
    {
        $variables = array_merge($this->variablesFor($subject), $extra);

        return [
            'subject' => Str::of((string) $template->subject)->replaceMatches('/{{\s*([\w_]+)\s*}}/', fn ($match) => $variables[$match[1]] ?? ''),
            'body' => Str::of($template->body)->replaceMatches('/{{\s*([\w_]+)\s*}}/', fn ($match) => $variables[$match[1]] ?? ''),
        ];
    }

    public function queue(Lead|Applicant $subject, AdmissionCommunicationTemplate $template, ?User $sender = null, array $extra = []): AdmissionCommunicationLog
    {
        $rendered = $this->render($template, $subject, $extra);

        return AdmissionCommunicationLog::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'template_id' => $template->id,
            'sent_by' => $sender?->id,
            'channel' => $template->channel,
            'provider' => $this->providerFor($template->channel),
            'recipient' => $this->recipientFor($subject, $template->channel),
            'subject_line' => (string) $rendered['subject'],
            'body' => (string) $rendered['body'],
            'status' => 'queued',
            'queued_at' => now(),
            'metadata' => ['variables' => $this->variablesFor($subject) + $extra],
        ]);
    }

    public function queueCampaign(iterable $subjects, AdmissionCommunicationTemplate $template, ?User $sender = null, array $extra = []): Collection
    {
        return collect($subjects)->map(fn ($subject) => $this->queue($subject, $template, $sender, $extra));
    }

    public function dispatchQueued(?int $limit = 50): int
    {
        return app(AdmissionIntegrationService::class)->dispatchQueued($limit);
    }

    public function manuallyLog(Lead|Applicant $subject, string $channel, string $body, ?User $actor = null): AdmissionCommunicationLog
    {
        return AdmissionCommunicationLog::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'sent_by' => $actor?->id,
            'channel' => $channel,
            'provider' => 'manual',
            'recipient' => $this->recipientFor($subject, $channel),
            'body' => $body,
            'status' => 'manually_logged',
            'sent_at' => now(),
        ]);
    }

    private function variablesFor(Lead|Applicant $subject): array
    {
        $isLead = $subject instanceof Lead;
        $user = $isLead ? null : $subject->user;

        return [
            'name' => $isLead ? $subject->name : ($user?->name ?? $subject->application_number),
            'applicant_name' => $user?->name ?? '',
            'lead_name' => $isLead ? $subject->name : '',
            'program' => $subject->program?->name ?? '',
            'batch' => $subject instanceof Applicant ? ($subject->batch?->name ?? '') : '',
            'status' => $subject->status ?? '',
            'deadline' => $subject->sla_due_at?->format('d M Y') ?? '',
            'counsellor' => ($subject instanceof Lead ? $subject->assignedTo : $subject->assignedCounsellor)?->name ?? '',
            'next_action' => $subject->next_action ?? '',
        ];
    }

    private function recipientFor(Model $subject, string $channel): ?string
    {
        if ($subject instanceof Lead) {
            return in_array($channel, ['sms', 'whatsapp'], true) ? $subject->phone : $subject->email;
        }

        $data = $subject instanceof Applicant ? ($subject->personal_data ?? []) : [];

        return in_array($channel, ['sms', 'whatsapp'], true)
            ? ($data['phone'] ?? $subject->user?->phone ?? null)
            : ($subject->user?->email ?? $data['email'] ?? null);
    }

    private function providerFor(string $channel): string
    {
        return match ($channel) {
            'email' => 'mail',
            'sms' => 'mock_sms',
            'whatsapp' => 'mock_whatsapp',
            default => app(AdmissionProviderRegistry::class)->active($channel)->provider_name,
        };
    }
}
