<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionIntegrationProvider extends Model
{
    protected $fillable = ['channel', 'provider_name', 'base_url', 'credential_keys', 'webhook_secret', 'is_active', 'sandbox_mode', 'timeout_seconds', 'retry_policy', 'metadata'];
    protected $casts = ['credential_keys' => 'array', 'retry_policy' => 'array', 'metadata' => 'array', 'is_active' => 'boolean', 'sandbox_mode' => 'boolean'];
}
