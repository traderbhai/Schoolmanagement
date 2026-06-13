<?php

namespace App\Services;

use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionProviderDeliveryAttempt;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;

class AdmissionIntegrationService
{
    public function __construct(private AdmissionProviderRegistry $providers, private AdmissionCommunicationService $communication) {}

    public function sendSandbox(Lead|Applicant $subject, string $channel, User $actor): AdmissionCommunicationLog
    {
        $template = AdmissionCommunicationTemplate::firstOrCreate(
            ['name' => 'Sandbox ' . strtoupper($channel) . ' Test', 'channel' => $channel],
            ['channel' => $channel, 'purpose' => 'sandbox_test', 'body' => 'Sandbox test for {{ name }}', 'variables' => ['name'], 'is_active' => true, 'created_by' => $actor->id]
        );

        $log = $this->communication->queue($subject, $template, $actor);
        $this->dispatchLog($log);

        return $log->refresh();
    }

    public function dispatchQueued(?int $limit = 50): int
    {
        return AdmissionCommunicationLog::where('status', 'queued')->oldest('queued_at')->limit($limit)->get()
            ->each(fn (AdmissionCommunicationLog $log) => $this->dispatchLog($log))
            ->count();
    }

    public function dispatchLog(AdmissionCommunicationLog $log): AdmissionCommunicationLog
    {
        $payload = ['recipient' => $log->recipient, 'body' => $log->body, 'subject' => $log->subject_line];
        $result = $this->providers->dispatchPayload($log->channel, $payload);

        AdmissionProviderDeliveryAttempt::create([
            'communication_log_id' => $log->id,
            'provider_name' => $result['provider'],
            'channel' => $log->channel,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'attempt_number' => ((int) $log->retry_count) + 1,
            'request_payload' => $payload,
            'response_payload' => $result['response'] ?? null,
            'failure_reason' => $result['failure_reason'] ?? null,
            'attempted_at' => now(),
        ]);

        $log->update($result['ok'] ? [
            'provider' => $result['provider'],
            'provider_request_id' => $result['request_id'],
            'provider_message_id' => $result['message_id'],
            'status' => 'sent',
            'delivery_state' => $result['delivery_state'],
            'sent_at' => now(),
            'last_synced_at' => now(),
        ] : [
            'provider' => $result['provider'],
            'status' => 'failed',
            'delivery_state' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $result['failure_reason'],
            'retry_count' => ((int) $log->retry_count) + 1,
        ]);

        return $log->refresh();
    }
}
