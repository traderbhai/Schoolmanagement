<?php

namespace App\Services;

use App\Models\AdmissionIntegrationProvider;
use Illuminate\Support\Collection;

class AdmissionVendorAdapterRegistry
{
    public function expectedAdapters(): array
    {
        return [
            'sms' => ['live' => 'msg91', 'sandbox' => 'sandbox_sms', 'env' => ['MSG91_AUTH_KEY', 'MSG91_SENDER_ID']],
            'whatsapp' => ['live' => 'meta_cloud_whatsapp', 'sandbox' => 'sandbox_whatsapp', 'env' => ['META_WHATSAPP_TOKEN', 'META_WHATSAPP_PHONE_ID']],
            'dialer' => ['live' => 'exotel', 'sandbox' => 'sandbox_dialer', 'env' => ['EXOTEL_SID', 'EXOTEL_TOKEN']],
            'video' => ['live' => 'zoom_google_meet', 'sandbox' => 'sandbox_video', 'env' => ['ZOOM_ACCOUNT_ID', 'GOOGLE_MEET_CLIENT_ID']],
            'signature' => ['live' => 'docusign_leegality', 'sandbox' => 'sandbox_signature', 'env' => ['DOCUSIGN_INTEGRATION_KEY', 'LEEGALITY_API_KEY']],
        ];
    }

    public function ensureDefaults(): Collection
    {
        return collect($this->expectedAdapters())->map(function (array $config, string $channel) {
            return AdmissionIntegrationProvider::updateOrCreate(
                ['channel' => $channel, 'provider_name' => $config['sandbox']],
                [
                    'base_url' => 'https://sandbox.local/' . $channel,
                    'credential_keys' => $config['env'],
                    'webhook_secret' => 'sandbox-secret-' . $channel,
                    'is_active' => true,
                    'sandbox_mode' => true,
                    'timeout_seconds' => 10,
                    'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => 60],
                    'metadata' => ['live_provider' => $config['live'], 'v' => '0.038'],
                ]
            );
        });
    }
}
