<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcReviewGovernanceRecord extends Model
{
    protected $fillable = ['record_type', 'meeting_id', 'work_item_id', 'title', 'body', 'owner_user_id', 'status', 'decision_type', 'due_at', 'closed_at', 'evidence', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'closed_at' => 'datetime', 'evidence' => 'array', 'metadata' => 'array'];

    public function meeting() { return $this->belongsTo(AcademicPmcReviewMeeting::class, 'meeting_id'); }
    public function workItem() { return $this->belongsTo(AcademicPmcWorkItem::class, 'work_item_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
