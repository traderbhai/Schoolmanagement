<?php

namespace App\Services;

use App\Models\AdmissionIntegrationProvider;
use Illuminate\Support\Facades\DB;

class AdmissionIntegrationHealthService
{
    public function checkAll()
    {
        app(AdmissionVendorAdapterRegistry::class)->ensureDefaults();

        return AdmissionIntegrationProvider::orderBy('channel')->get()->map(fn ($provider) => $this->check($provider));
    }

    public function check(AdmissionIntegrationProvider $provider): object
    {
        $keys = collect($provider->credential_keys ?? []);
        $credentialsPresent = $keys->isNotEmpty() && $keys->every(fn ($key) => filled(env($key)));
        $status = $provider->sandbox_mode
            ? 'sandbox_only'
            : ($credentialsPresent ? 'ready' : 'missing_credentials');

        $id = DB::table('admission_integration_health_checks')->insertGetId([
            'provider_id' => $provider->id,
            'status' => $status,
            'credentials_present' => $credentialsPresent,
            'base_url_reachable' => $provider->sandbox_mode || filled($provider->base_url),
            'last_success_at' => $status === 'ready' || $status === 'sandbox_only' ? now() : null,
            'last_failure_reason' => $status === 'missing_credentials' ? 'Credential environment keys are not configured.' : null,
            'checked_at' => now(),
            'metadata' => json_encode(['provider' => $provider->provider_name]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('admission_integration_health_checks')->where('id', $id)->first();
    }

    public function retryFailed(int $retryId): void
    {
        $row = DB::table('admission_integration_retry_queue')->where('id', $retryId)->first();
        if (! $row || $row->attempts >= $row->max_attempts) {
            return;
        }

        DB::table('admission_integration_retry_queue')->where('id', $retryId)->update([
            'attempts' => $row->attempts + 1,
            'status' => $row->attempts + 1 >= $row->max_attempts ? 'exhausted' : 'queued',
            'next_retry_at' => now()->addMinutes(15),
            'updated_at' => now(),
        ]);
    }
}
