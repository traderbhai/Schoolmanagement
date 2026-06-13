<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCommunicationLog extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'template_id', 'sent_by', 'channel', 'provider',
        'recipient', 'subject_line', 'body', 'status', 'queued_at', 'sent_at',
        'failed_at', 'failure_reason', 'metadata',
    ];

    protected $casts = ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'metadata' => 'array'];

    public function subject() { return $this->morphTo(); }
    public function template() { return $this->belongsTo(AdmissionCommunicationTemplate::class, 'template_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
