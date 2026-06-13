<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCommunicationLog extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'template_id', 'sent_by', 'channel', 'provider',
        'provider_message_id', 'provider_request_id', 'recipient', 'subject_line', 'body',
        'status', 'delivery_state', 'queued_at', 'sent_at', 'failed_at', 'failure_reason',
        'retry_count', 'last_synced_at', 'webhook_payload', 'metadata',
    ];

    protected $casts = [
        'queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime',
        'last_synced_at' => 'datetime', 'webhook_payload' => 'array', 'metadata' => 'array',
    ];

    public function subject() { return $this->morphTo(); }
    public function template() { return $this->belongsTo(AdmissionCommunicationTemplate::class, 'template_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
