<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdmissionReminderSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type', 'subject_id', 'cadence_rule_id', 'template_id', 'owner_user_id',
        'assigned_to', 'target', 'reason', 'channel', 'status', 'priority', 'due_at',
        'sent_at', 'completed_at', 'cancelled_at', 'paused_at', 'escalated_at',
        'escalated_to', 'attempt_count', 'repeat_rule', 'notes', 'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'paused_at' => 'datetime',
        'escalated_at' => 'datetime',
        'repeat_rule' => 'array',
        'metadata' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function cadenceRule(): BelongsTo
    {
        return $this->belongsTo(AdmissionCadenceRule::class, 'cadence_rule_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AdmissionCommunicationTemplate::class, 'template_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }
}
