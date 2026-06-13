<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAutomationExecution extends Model
{
    protected $fillable = [
        'automation_id', 'subject_type', 'subject_id', 'idempotency_key',
        'status', 'actions_taken', 'failure_reason',
    ];

    protected $casts = ['actions_taken' => 'array'];

    public function subject() { return $this->morphTo(); }
    public function automation() { return $this->belongsTo(AdmissionAutomation::class, 'automation_id'); }
}
