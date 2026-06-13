<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionIntegrationWebhookEvent extends Model
{
    protected $fillable = ['provider_name', 'event_type', 'external_id', 'subject_type', 'subject_id', 'communication_log_id', 'status', 'payload', 'processed_at'];
    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
    public function subject() { return $this->morphTo(); }
    public function communicationLog() { return $this->belongsTo(AdmissionCommunicationLog::class); }
}
