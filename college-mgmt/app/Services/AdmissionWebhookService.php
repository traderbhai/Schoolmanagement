<?php

namespace App\Services;

use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionIntegrationWebhookEvent;

class AdmissionWebhookService
{
    public function record(string $provider, string $eventType, array $payload): AdmissionIntegrationWebhookEvent
    {
        $externalId = $payload['message_id'] ?? $payload['external_id'] ?? null;
        $log = $externalId ? AdmissionCommunicationLog::where('provider_message_id', $externalId)->first() : null;

        $event = AdmissionIntegrationWebhookEvent::create([
            'provider_name' => $provider,
            'event_type' => $eventType,
            'external_id' => $externalId,
            'subject_type' => $log?->subject_type ?? ($payload['subject_type'] ?? AdmissionCommunicationLog::class),
            'subject_id' => $log?->subject_id ?? ($payload['subject_id'] ?? 0),
            'communication_log_id' => $log?->id,
            'status' => 'processed',
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        if ($log) {
            $log->update([
                'delivery_state' => $payload['delivery_state'] ?? $eventType,
                'webhook_payload' => $payload,
                'last_synced_at' => now(),
            ]);
        }

        return $event;
    }
}
