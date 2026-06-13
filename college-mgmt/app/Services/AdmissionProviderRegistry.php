<?php

namespace App\Services;

use App\Models\AdmissionIntegrationProvider;
use Illuminate\Support\Str;

class AdmissionProviderRegistry
{
    public function active(string $channel): AdmissionIntegrationProvider
    {
        return AdmissionIntegrationProvider::where('channel', $channel)->where('is_active', true)->first()
            ?: AdmissionIntegrationProvider::create([
                'channel' => $channel,
                'provider_name' => 'sandbox_' . $channel,
                'sandbox_mode' => true,
                'is_active' => true,
                'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => 30],
                'metadata' => ['auto_created' => true],
            ]);
    }

    public function dispatchPayload(string $channel, array $payload): array
    {
        $provider = $this->active($channel);
        if (! $provider->sandbox_mode && blank($provider->credential_keys)) {
            return ['ok' => false, 'provider' => $provider->provider_name, 'failure_reason' => 'Provider credentials are not configured.'];
        }

        return [
            'ok' => true,
            'provider' => $provider->provider_name,
            'request_id' => 'req_' . Str::uuid(),
            'message_id' => 'msg_' . Str::uuid(),
            'delivery_state' => $provider->sandbox_mode ? 'sandbox_sent' : 'sent',
            'response' => ['sandbox' => $provider->sandbox_mode, 'accepted' => true, 'payload' => $payload],
        ];
    }

    public function allStatuses()
    {
        return AdmissionIntegrationProvider::orderBy('channel')->orderBy('provider_name')->get();
    }
}
