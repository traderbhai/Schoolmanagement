<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssignmentEvent extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'from_user_id', 'to_user_id', 'assigned_by',
        'mode', 'reason', 'notes', 'sla_before', 'sla_after', 'metadata',
    ];

    protected $casts = [
        'sla_before' => 'datetime',
        'sla_after' => 'datetime',
        'metadata' => 'array',
    ];

    public function subject() { return $this->morphTo(); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser() { return $this->belongsTo(User::class, 'to_user_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
}
