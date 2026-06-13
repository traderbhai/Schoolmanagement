<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionProviderDeliveryAttempt extends Model
{
    protected $fillable = ['communication_log_id', 'provider_name', 'channel', 'status', 'attempt_number', 'request_payload', 'response_payload', 'failure_reason', 'attempted_at'];
    protected $casts = ['request_payload' => 'array', 'response_payload' => 'array', 'attempted_at' => 'datetime'];
    public function communicationLog() { return $this->belongsTo(AdmissionCommunicationLog::class); }
}
