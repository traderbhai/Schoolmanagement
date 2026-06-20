<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanActionItem extends Model
{
    protected $fillable = [
        'meeting_id', 'title', 'description', 'source_type', 'source_key',
        'owner_user_id', 'assigned_by', 'priority', 'due_at', 'status',
        'closure_note', 'closed_at', 'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function meeting() { return $this->belongsTo(AcademicDeanReviewMeeting::class, 'meeting_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function evidence() { return $this->hasMany(AcademicDeanActionEvidence::class, 'action_item_id'); }
}
